<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use App\Domain\Persediaan\Data\DataStokAwal;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Layanan\PemeriksaStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membuat atau mengubah draf stok awal (F-05a, DesainF05a C.6.1).
 *
 * - Buat idempoten per Uuid klien: Uuid yang sama mengembalikan dokumen yang sudah ada, apa adanya.
 * - Ubah hanya untuk status Draf (`StatusTidakSesuai`) dan hanya bila `versiDiubahPada` sama dengan `DiubahPada`
 *   tersimpan (`DokumenBerubah`: dokumen diubah orang lain sejak dibuka).
 * - Isi diperiksa `PemeriksaStokAwal`; semua baris diganti dengan snapshot NamaProduk/Sku, `Nilai` = Jumlah ×
 *   HppSatuan (2 desimal, HalfUp), lalu JumlahBaris & TotalNilai dihitung ulang.
 * - Dokumen draf tidak pernah dihapus; draf yang tidak dipakai dibuang (`BuangStokAwal`).
 *
 * Audit `stok-awal.buat` / `stok-awal.ubah`. Dipakai juga oleh impor stok awal (Tim E, `Sumber = Impor`).
 */
final class SimpanStokAwal
{
    private const UKURAN_POTONGAN = 500;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaStokAwal $pemeriksa,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataStokAwal $data, ?StokAwal $ada): StokAwal
    {
        $data = self::Normalkan($data);

        if ($ada === null && $data->uuid !== null) {
            $lama = StokAwal::query()->where('Uuid', $data->uuid)->first();

            if ($lama !== null) {
                return $lama;
            }
        }

        try {
            return DB::transaction(
                fn (): StokAwal => $ada === null ? $this->Buat($data) : $this->Ubah($data, $ada),
                max(1, (int) config('persediaan.PercobaanTransaksi', 3)),
            );
        } catch (UniqueConstraintViolationException $galat) {
            // Kirim ganda bersamaan dengan Uuid klien yang sama: yang kalah mengembalikan dokumen pemenang.
            $lama = $ada === null && $data->uuid !== null ? StokAwal::query()->where('Uuid', $data->uuid)->first() : null;

            return $lama ?? throw self::TerjemahkanGalatUnik($galat);
        }
    }

    /**
     * Pelanggaran indeks unik yang tidak bisa dipulihkan menjadi galat bisnis (422), bukan galat SQL (500):
     * - Uuid klien sudah dipakai dokumen di luar tenant ini (Uuid unik global; dokumennya tidak terlihat di sini);
     * - dua baris dengan produk & nomor batch yang dianggap sama oleh kolasi database (beda aksen, misal É/E),
     *   yang lolos pemeriksaan `BarisGanda` berbasis huruf kecil.
     */
    private static function TerjemahkanGalatUnik(UniqueConstraintViolationException $galat): Throwable
    {
        $pesan = $galat->getMessage();

        if (str_contains($pesan, 'UniqStokAwalUuid')) {
            return new PelanggaranAturanBisnis('UuidSudahDipakai', 'Draf ini tidak bisa disimpan dengan kode yang sama. Muat ulang halaman lalu simpan lagi.', 'Uuid');
        }

        if (str_contains($pesan, 'UniqStokAwalDetail')) {
            return new PelanggaranAturanBisnis('BarisGanda', 'Ada baris dengan produk dan nomor batch yang sama (beda aksen atau spasi dianggap sama). Gabungkan barisnya.', 'Baris');
        }

        return $galat;
    }

    private function Buat(DataStokAwal $data): StokAwal
    {
        $hasil = $this->pemeriksa->Periksa($data->idGudang, $data->tanggal, $data->baris);
        $oleh = $this->audit->AmbilIdPengguna();

        $stokAwal = new StokAwal;
        $stokAwal->fill([
            'IdGudang' => $data->idGudang,
            'IdOutlet' => $hasil['Gudang']->idOutlet,
            'Tanggal' => $data->tanggal->format('Y-m-d'),
            'Status' => StatusStokAwal::Draf,
            'Sumber' => $data->sumber,
            'IdImporStokAwal' => $data->idImpor,
            'Catatan' => $data->catatan,
            'DibuatOleh' => $oleh,
            'DiubahOleh' => $oleh,
        ]);

        if ($data->uuid !== null) {
            $stokAwal->Uuid = $data->uuid;
        }

        $stokAwal->save();
        $this->GantiBaris($stokAwal, $data->baris, $hasil['Produk']);
        $this->riwayat->Catat(StokAwal::JENIS_DOKUMEN, $stokAwal->Id, null, StatusStokAwal::Draf->value, $oleh);
        $this->audit->Catat('stok-awal.buat', $stokAwal, null, self::Ringkas($stokAwal, $hasil['Gudang']->nama));

        return $stokAwal;
    }

    private function Ubah(DataStokAwal $data, StokAwal $ada): StokAwal
    {
        $stokAwal = StokAwal::query()->whereKey($ada->Id)->lockForUpdate()->firstOrFail();

        if ($stokAwal->Status !== StatusStokAwal::Draf) {
            throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Stok awal berstatus {$stokAwal->Status->AmbilLabel()} tidak bisa diubah. Hanya draf yang bisa diubah.");
        }

        if (! self::CekVersiSama($data->versiDiubahPada, $stokAwal)) {
            throw new PelanggaranAturanBisnis('DokumenBerubah', 'Draf ini baru saja diubah orang lain. Muat ulang halaman, lalu ulangi perubahan Anda.');
        }

        $hasil = $this->pemeriksa->Periksa($data->idGudang, $data->tanggal, $data->baris);
        $lama = self::Ringkas($stokAwal, null);

        $stokAwal->fill([
            'IdGudang' => $data->idGudang,
            'IdOutlet' => $hasil['Gudang']->idOutlet,
            'Tanggal' => $data->tanggal->format('Y-m-d'),
            'Catatan' => $data->catatan,
            'PesanGalat' => null,
            'DiubahOleh' => $this->audit->AmbilIdPengguna(),
        ]);
        $stokAwal->Detail()->delete();
        $this->GantiBaris($stokAwal, $data->baris, $hasil['Produk']);
        $this->audit->Catat('stok-awal.ubah', $stokAwal, $lama, self::Ringkas($stokAwal, $hasil['Gudang']->nama));

        return $stokAwal;
    }

    /**
     * Menyisipkan baris (bulk per potongan), lalu menyimpan JumlahBaris & TotalNilai dokumen.
     *
     * @param  list<DataBarisStokAwal>  $baris
     * @param  array<int, DataInfoProdukStok>  $produk
     */
    private function GantiBaris(StokAwal $stokAwal, array $baris, array $produk): void
    {
        $idTenant = $this->konteks->Wajib();
        $waktu = now();
        $total = Uang::Nol();
        $isi = [];

        foreach ($baris as $indeks => $satu) {
            $info = $produk[$satu->idProduk];
            $nilai = AritmetikaHpp::Nilai($satu->jumlah, $satu->hppSatuan);
            $total = $total->Tambah($nilai);
            $isi[] = [
                'IdTenant' => $idTenant,
                'IdStokAwal' => $stokAwal->Id,
                'Urutan' => $indeks + 1,
                'IdProduk' => $satu->idProduk,
                'NamaProduk' => mb_substr($info->nama, 0, 150),
                'Sku' => $info->sku,
                'Jumlah' => $satu->jumlah->KeString(),
                'HppSatuan' => (string) $satu->hppSatuan->toScale(6),
                'Nilai' => $nilai->KeString(),
                'NomorBatch' => $satu->nomorBatch,
                'TanggalKedaluwarsa' => $satu->tanggalKedaluwarsa?->format('Y-m-d'),
                'DaftarNomorSeri' => $satu->nomorSeri === [] ? null : json_encode($satu->nomorSeri, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'DibuatPada' => $waktu,
                'DiubahPada' => $waktu,
            ];
        }

        foreach (array_chunk($isi, self::UKURAN_POTONGAN) as $potongan) {
            StokAwalDetail::query()->insert($potongan);
        }

        $stokAwal->JumlahBaris = count($baris);
        $stokAwal->TotalNilai = $total->KeString();
        // Selalu menyentuh DiubahPada supaya versi optimistis berubah walau isinya sama.
        $stokAwal->DiubahPada = $waktu;
        $stokAwal->save();
    }

    /** Nomor batch & seri dirapikan (spasi tepi; kosong = tidak ada) sebelum diperiksa dan disimpan. */
    private static function Normalkan(DataStokAwal $data): DataStokAwal
    {
        $baris = array_map(function (DataBarisStokAwal $b): DataBarisStokAwal {
            $batch = $b->nomorBatch === null ? null : trim($b->nomorBatch);
            $seri = array_values(array_filter(array_map('trim', $b->nomorSeri), fn (string $s): bool => $s !== ''));

            return new DataBarisStokAwal($b->idProduk, $b->jumlah, $b->hppSatuan, $batch === '' ? null : $batch, $b->tanggalKedaluwarsa, $seri);
        }, $data->baris);
        $catatan = $data->catatan === null ? null : trim($data->catatan);

        return new DataStokAwal(
            $data->uuid,
            $data->idGudang,
            $data->tanggal,
            $catatan === '' ? null : $catatan,
            array_values($baris),
            $data->sumber,
            $data->idImpor,
            $data->versiDiubahPada,
        );
    }

    private static function CekVersiSama(?string $versi, StokAwal $stokAwal): bool
    {
        if ($versi === null || $versi === '' || $stokAwal->DiubahPada === null) {
            return false;
        }

        try {
            return CarbonImmutable::parse($versi)->getTimestamp() === $stokAwal->DiubahPada->getTimestamp();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function Ringkas(StokAwal $stokAwal, ?string $namaGudang): array
    {
        return array_filter([
            'IdGudang' => $stokAwal->IdGudang,
            'NamaGudang' => $namaGudang,
            'Tanggal' => $stokAwal->Tanggal->format('Y-m-d'),
            'Sumber' => $stokAwal->Sumber->value,
            'JumlahBaris' => $stokAwal->JumlahBaris,
            'TotalNilai' => $stokAwal->TotalNilai,
        ], fn (mixed $nilai): bool => $nilai !== null);
    }
}
