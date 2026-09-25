<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Persediaan\Data\DataBarisDokumenStok;
use App\Domain\Persediaan\Data\DataPenyesuaianStok;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Layanan\PemeriksaBarisStok;
use App\Domain\Persediaan\Layanan\PemeriksaLokasiDokumen;
use App\Domain\Persediaan\Layanan\PenaksirNilaiPenyesuaian;
use App\Domain\Persediaan\Layanan\PenjagaVersiDokumen;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStokDetail;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah draf penyesuaian stok (F-05b). Buat idempoten per Uuid klien; ubah hanya Draf dengan versi
 * optimistis. Lokasi aktif (bukan dalam perjalanan), tanggal tidak di masa depan, alasan wajib (`Lainnya` wajib
 * keterangan; hanya `Lainnya` yang boleh menambah stok). Baris diperiksa `PemeriksaBarisStok`. `NilaiPerkiraan`
 * dihitung ulang (petunjuk batas persetujuan). Draf belum mengubah stok. Audit `penyesuaian-stok.buat`/`.ubah`.
 */
final class SimpanPenyesuaianStok
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly PemeriksaBarisStok $pemeriksaBaris,
        private readonly PenaksirNilaiPenyesuaian $penaksir,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPenyesuaianStok $data, ?PenyesuaianStok $ada): PenyesuaianStok
    {
        if ($ada === null && $data->uuid !== null) {
            $lama = PenyesuaianStok::query()->where('Uuid', $data->uuid)->first();

            if ($lama !== null) {
                return $lama;
            }
        }

        return DB::transaction(function () use ($data, $ada): PenyesuaianStok {
            $oleh = $this->audit->AmbilIdPengguna();

            if ($ada !== null) {
                $dokumen = PenyesuaianStok::query()->whereKey($ada->Id)->lockForUpdate()->firstOrFail();

                if ($dokumen->Status !== StatusPenyesuaianStok::Draf) {
                    throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Penyesuaian berstatus {$dokumen->Status->AmbilLabel()} tidak bisa diubah. Hanya draf yang bisa diubah.");
                }

                PenjagaVersiDokumen::Pastikan($data->versiDiubahPada, $dokumen->DiubahPada);
            } else {
                $dokumen = new PenyesuaianStok;

                if ($data->uuid !== null) {
                    $dokumen->Uuid = $data->uuid;
                }
            }

            $gudang = $this->pemeriksaLokasi->AmbilLokasi($data->idGudang);
            $this->pemeriksaLokasi->PastikanBukanMasaDepan($data->tanggal, $gudang->idOutlet);
            $keterangan = $data->keterangan === null ? null : trim($data->keterangan);
            $keterangan = $keterangan === '' ? null : $keterangan;

            if ($data->alasan->CekWajibKeterangan() && ($keterangan === null || mb_strlen($keterangan) < 5)) {
                throw new PelanggaranAturanBisnis('KeteranganWajib', 'Alasan "Lainnya" wajib disertai keterangan minimal 5 karakter.', 'Keterangan');
            }

            $produk = $this->pemeriksaBaris->Periksa($data->baris, $gudang->id, false, $data->alasan->CekBolehMasuk());
            $lama = $ada === null ? null : ['JumlahBaris' => $dokumen->JumlahBaris, 'KodeAlasan' => $dokumen->KodeAlasan->value, 'NilaiPerkiraan' => $dokumen->NilaiPerkiraan];

            $dokumen->fill([
                'IdGudang' => $gudang->id,
                'IdOutlet' => $gudang->idOutlet,
                'Tanggal' => $data->tanggal->format('Y-m-d'),
                'KodeAlasan' => $data->alasan,
                'Keterangan' => $keterangan,
                'JumlahBaris' => count($data->baris),
                'NilaiPerkiraan' => $this->penaksir->Taksir($data->baris, $gudang->id)->KeString(),
                'AlasanTolak' => null,
                'DiubahOleh' => $oleh,
            ]);

            if ($ada === null) {
                $dokumen->DibuatOleh = $oleh;
            }

            $dokumen->DiubahPada = now();
            $dokumen->save();

            if ($ada !== null) {
                PenyesuaianStokDetail::query()->where('IdPenyesuaianStok', $dokumen->Id)->get()->each(fn (PenyesuaianStokDetail $d) => $d->delete());
            }

            $this->SimpanBaris($dokumen, $data->baris, $produk);
            $ringkas = ['IdGudang' => $gudang->id, 'NamaGudang' => $gudang->nama, 'KodeAlasan' => $data->alasan->value, 'JumlahBaris' => $dokumen->JumlahBaris, 'NilaiPerkiraan' => $dokumen->NilaiPerkiraan];

            if ($ada === null) {
                $this->riwayat->Catat(PenyesuaianStok::JENIS_DOKUMEN, $dokumen->Id, null, StatusPenyesuaianStok::Draf->value, $oleh);
                $this->audit->Catat('penyesuaian-stok.buat', $dokumen, null, $ringkas);
            } else {
                $this->audit->Catat('penyesuaian-stok.ubah', $dokumen, $lama, $ringkas);
            }

            return $dokumen;
        }, max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
    }

    /**
     * @param  list<DataBarisDokumenStok>  $baris
     * @param  array<int, DataInfoProdukStok>  $produk
     */
    private function SimpanBaris(PenyesuaianStok $dokumen, array $baris, array $produk): void
    {
        $idTenant = $this->konteks->Wajib();
        $batch = BatchStok::query()->whereIn('Id', array_values(array_filter(array_map(fn (DataBarisDokumenStok $b): ?int => $b->idBatchStok, $baris))))->get()->keyBy('Id');
        $seri = NomorSeri::query()->whereIn('Id', array_values(array_filter(array_map(fn (DataBarisDokumenStok $b): ?int => $b->idNomorSeri, $baris))))->get()->keyBy('Id');
        $waktu = now();
        $isi = [];

        foreach ($baris as $i => $b) {
            $info = $produk[$b->idProduk];
            $batchKeluar = $b->idBatchStok === null ? null : $batch->get($b->idBatchStok);
            $isi[] = [
                'IdTenant' => $idTenant,
                'IdPenyesuaianStok' => $dokumen->Id,
                'Urutan' => $i + 1,
                'IdProduk' => $b->idProduk,
                'NamaProduk' => mb_substr($info->nama, 0, 150),
                'Sku' => $info->sku,
                'Jumlah' => $b->jumlah->KeString(),
                'HppSatuan' => $b->hppSatuan === null ? null : (string) $b->hppSatuan->toScale(6),
                'IdBatchStok' => $batchKeluar?->Id,
                'NomorBatch' => $batchKeluar->NomorBatch ?? ($b->nomorBatch === null ? null : trim($b->nomorBatch)),
                'TanggalKedaluwarsa' => $batchKeluar?->TanggalKedaluwarsa?->format('Y-m-d') ?? $b->tanggalKedaluwarsa?->format('Y-m-d'),
                'IdNomorSeri' => $b->idNomorSeri,
                'NomorSeri' => $b->idNomorSeri === null ? ($b->nomorSeri === null ? null : trim($b->nomorSeri)) : $seri->get($b->idNomorSeri)?->Nomor,
                'DibuatPada' => $waktu,
                'DiubahPada' => $waktu,
            ];
        }

        PenyesuaianStokDetail::query()->insert($isi);
    }
}
