<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;

/**
 * Detail dokumen stok awal (DesainF05a C.6.6, tipe FE `PropsDetailStokAwal` bagian StokAwal/Baris/Jurnal/Riwayat)
 * dan isi form ubah draf (`PropsFormStokAwal['StokAwal']`). Tindakan, izin, dan kesiapan akun dilengkapi kontroler.
 * `VersiDiubahPada` = `DiubahPada` ISO, dikirim balik saat menyimpan untuk menolak `DokumenBerubah`.
 */
final class DetailStokAwal
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
        private readonly DaftarAnggota $anggota,
        private readonly JurnalSumber $jurnalSumber,
        private readonly PetaAkunPersediaan $petaAkun,
    ) {}

    /**
     * @return array{StokAwal: array<string, mixed>, Baris: list<array<string, mixed>>, Jurnal: list<array<string, mixed>>, Riwayat: list<array<string, mixed>>}
     */
    public function Ambil(StokAwal $stokAwal): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$stokAwal->IdGudang])[$stokAwal->IdGudang] ?? null;
        $riwayat = RiwayatStatusDokumen::query()
            ->where('JenisDokumen', StokAwal::JENIS_DOKUMEN)
            ->where('IdDokumen', $stokAwal->Id)
            ->orderBy('Id')
            ->get();
        $idPengguna = [$stokAwal->DibuatOleh, $stokAwal->DipostingOleh, $stokAwal->DibatalkanOleh, ...$riwayat->pluck('DiubahOleh')->all()];
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_filter($idPengguna, 'is_int'))));
        $namaDari = fn (?int $id): ?string => $id === null ? null : ($nama[$id] ?? null);
        $detail = $this->AmbilDetail($stokAwal);
        $produk = $this->AmbilProduk($detail);

        return [
            'StokAwal' => [
                'Uuid' => $stokAwal->Uuid,
                'Nomor' => $stokAwal->Nomor,
                'Status' => $stokAwal->Status->value,
                'LabelStatus' => $stokAwal->Status->AmbilLabel(),
                'Sumber' => $stokAwal->Sumber->value,
                'UuidImpor' => $stokAwal->ImporStokAwal?->Uuid,
                'NamaGudang' => $gudang->nama ?? '',
                'NamaOutlet' => $gudang?->namaOutlet,
                'Tanggal' => $stokAwal->Tanggal->format('Y-m-d'),
                'Catatan' => $stokAwal->Catatan,
                'JumlahBaris' => $stokAwal->JumlahBaris,
                'TotalNilai' => $stokAwal->TotalNilai,
                'PesanGalat' => $stokAwal->PesanGalat,
                'DibuatOleh' => $namaDari($stokAwal->DibuatOleh),
                'DibuatPada' => $stokAwal->DibuatPada?->toIso8601String() ?? '',
                'DipostingOleh' => $namaDari($stokAwal->DipostingOleh),
                'DipostingPada' => $stokAwal->DipostingPada?->toIso8601String(),
                'DibatalkanOleh' => $namaDari($stokAwal->DibatalkanOleh),
                'DibatalkanPada' => $stokAwal->DibatalkanPada?->toIso8601String(),
                'AlasanBatal' => $stokAwal->AlasanBatal,
                'VersiDiubahPada' => $stokAwal->DiubahPada?->toIso8601String() ?? '',
            ],
            'Baris' => array_map(fn (StokAwalDetail $b): array => [
                'Urutan' => $b->Urutan,
                'UuidProduk' => $produk[$b->IdProduk]->uuid ?? '',
                'NamaProduk' => $b->NamaProduk,
                'Sku' => $b->Sku,
                'SimbolSatuan' => $produk[$b->IdProduk]->simbolSatuan ?? '',
                'Pelacakan' => ($produk[$b->IdProduk] ?? null)?->pelacakan->value ?? 'Tidak',
                'Jumlah' => $b->Jumlah,
                'HppSatuan' => $b->HppSatuan,
                'Nilai' => $b->Nilai,
                'NomorBatch' => $b->NomorBatch,
                'TanggalKedaluwarsa' => $b->TanggalKedaluwarsa?->format('Y-m-d'),
                'NomorSeri' => array_values($b->DaftarNomorSeri ?? []),
            ], $detail),
            'Jurnal' => $this->jurnalSumber->Ambil(JenisSumberJurnal::StokAwal, $stokAwal->Id),
            'Riwayat' => array_values($riwayat->map(fn (RiwayatStatusDokumen $r): array => [
                'StatusDari' => $r->StatusDari,
                'StatusKe' => $r->StatusKe,
                'LabelStatusKe' => StatusStokAwal::tryFrom($r->StatusKe)?->AmbilLabel() ?? $r->StatusKe,
                'Oleh' => $namaDari($r->DiubahOleh),
                'Pada' => $r->DiubahPada?->toIso8601String() ?? '',
                'Alasan' => $r->Alasan,
            ])->all()),
        ];
    }

    /**
     * Isi form ubah draf (tipe FE `PropsFormStokAwal['StokAwal']`) dengan saldo produk di lokasi dokumen.
     *
     * @return array{Uuid: string, UuidGudang: string, Tanggal: string, Catatan: string|null, VersiDiubahPada: string, Baris: list<array<string, mixed>>}
     */
    public function AmbilForm(StokAwal $stokAwal): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$stokAwal->IdGudang])[$stokAwal->IdGudang] ?? null;
        $detail = $this->AmbilDetail($stokAwal);
        $produk = $this->AmbilProduk($detail);
        $saldo = SaldoStok::query()
            ->where('IdGudang', $stokAwal->IdGudang)
            ->whereIn('IdProduk', array_map(fn (StokAwalDetail $b): int => $b->IdProduk, $detail))
            ->pluck('JumlahTersedia', 'IdProduk')
            ->all();

        return [
            'Uuid' => $stokAwal->Uuid,
            'UuidGudang' => $gudang->uuid ?? '',
            'Tanggal' => $stokAwal->Tanggal->format('Y-m-d'),
            'Catatan' => $stokAwal->Catatan,
            'VersiDiubahPada' => $stokAwal->DiubahPada?->toIso8601String() ?? '',
            'Baris' => array_map(fn (StokAwalDetail $b): array => [
                'UuidProduk' => $produk[$b->IdProduk]->uuid ?? '',
                'NamaProduk' => $b->NamaProduk,
                'Sku' => $b->Sku,
                'SimbolSatuan' => $produk[$b->IdProduk]->simbolSatuan ?? '',
                'BolehDesimal' => $produk[$b->IdProduk]->bolehDesimal ?? false,
                'Pelacakan' => ($produk[$b->IdProduk] ?? null)?->pelacakan->value ?? 'Tidak',
                'SaldoDiGudang' => isset($saldo[$b->IdProduk]) ? (string) $saldo[$b->IdProduk] : '0.0000',
                'Jumlah' => $b->Jumlah,
                'HppSatuan' => $b->HppSatuan,
                'NomorBatch' => $b->NomorBatch,
                'TanggalKedaluwarsa' => $b->TanggalKedaluwarsa?->format('Y-m-d'),
                'NomorSeri' => array_values($b->DaftarNomorSeri ?? []),
            ], $detail),
        ];
    }

    /**
     * Peran akun yang dibutuhkan posting dokumen ini (persediaan per jenis produk + Ekuitas Saldo Awal), untuk
     * panel kesiapan akun.
     *
     * @return list<PeranAkun>
     */
    public function AmbilPeranAkun(StokAwal $stokAwal): array
    {
        $peran = [];

        foreach ($this->AmbilProduk($this->AmbilDetail($stokAwal)) as $info) {
            $peran[$this->petaAkun->UntukJenis($info->jenis)->value] = true;
        }

        $peran[PeranAkun::EkuitasSaldoAwal->value] = true;

        return array_map(fn (string $p): PeranAkun => PeranAkun::from($p), array_keys($peran));
    }

    /**
     * @return list<StokAwalDetail>
     */
    private function AmbilDetail(StokAwal $stokAwal): array
    {
        return array_values(StokAwalDetail::query()->where('IdStokAwal', $stokAwal->Id)->orderBy('Urutan')->get()->all());
    }

    /**
     * @param  list<StokAwalDetail>  $detail
     * @return array<int, DataInfoProdukStok>
     */
    private function AmbilProduk(array $detail): array
    {
        return $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (StokAwalDetail $b): int => $b->IdProduk, $detail))), denganTerhapus: true);
    }
}
