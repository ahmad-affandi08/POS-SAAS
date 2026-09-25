<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStokDetail;

/**
 * Detail penyesuaian stok (tipe FE `PropsDetailPenyesuaianStok`) dan isi form ubah draf
 * (`PropsFormPenyesuaianStok['Penyesuaian']`). Tindakan & izin dilengkapi kontroler.
 */
final class DetailPenyesuaianStok
{
    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
        private readonly JurnalSumber $jurnalSumber,
        private readonly RiwayatDokumenPersediaan $riwayat,
        private readonly PelacakanTersedia $pelacakan,
    ) {}

    /**
     * @return array{Penyesuaian: array<string, mixed>, Baris: list<array<string, mixed>>, Jurnal: list<array<string, mixed>>, Riwayat: list<array<string, mixed>>}
     */
    public function Ambil(PenyesuaianStok $p): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$p->IdGudang])[$p->IdGudang] ?? null;
        [$riwayat, $nama] = $this->riwayat->Ambil(PenyesuaianStok::JENIS_DOKUMEN, $p->Id, fn (string $s): string => StatusPenyesuaianStok::tryFrom($s)?->AmbilLabel() ?? $s, [$p->DibuatOleh, $p->DiajukanOleh, $p->DisetujuiOleh, $p->DipostingOleh]);

        return [
            'Penyesuaian' => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'NamaGudang' => $gudang->nama ?? '',
                'NamaOutlet' => $gudang?->namaOutlet,
                'Tanggal' => $p->Tanggal->format('Y-m-d'),
                'KodeAlasan' => $p->KodeAlasan->value,
                'LabelAlasan' => $p->KodeAlasan->AmbilLabel(),
                'Keterangan' => $p->Keterangan,
                'JumlahBaris' => $p->JumlahBaris,
                'NilaiPerkiraan' => $p->NilaiPerkiraan,
                'TotalNilaiMasuk' => $p->TotalNilaiMasuk,
                'TotalNilaiKeluar' => $p->TotalNilaiKeluar,
                'PerluPersetujuan' => $p->PerluPersetujuan,
                'AlasanTolak' => $p->AlasanTolak,
                'DibuatOleh' => $nama($p->DibuatOleh),
                'DibuatPada' => $p->DibuatPada?->toIso8601String() ?? '',
                'DiajukanOleh' => $nama($p->DiajukanOleh),
                'DisetujuiOleh' => $nama($p->DisetujuiOleh),
                'DipostingOleh' => $nama($p->DipostingOleh),
                'DipostingPada' => $p->DipostingPada?->toIso8601String(),
                'VersiDiubahPada' => $p->DiubahPada?->toIso8601String() ?? '',
            ],
            'Baris' => $this->AmbilBaris($p),
            'Jurnal' => $this->jurnalSumber->Ambil(JenisSumberJurnal::PenyesuaianStok, $p->Id),
            'Riwayat' => $riwayat,
        ];
    }

    /**
     * @return array{Uuid: string, UuidGudang: string, Tanggal: string, KodeAlasan: string, Keterangan: string|null, VersiDiubahPada: string, Baris: list<array<string, mixed>>}
     */
    public function AmbilForm(PenyesuaianStok $p): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$p->IdGudang])[$p->IdGudang] ?? null;

        return [
            'Uuid' => $p->Uuid,
            'UuidGudang' => $gudang->uuid ?? '',
            'Tanggal' => $p->Tanggal->format('Y-m-d'),
            'KodeAlasan' => $p->KodeAlasan->value,
            'Keterangan' => $p->Keterangan,
            'VersiDiubahPada' => $p->DiubahPada?->toIso8601String() ?? '',
            'Baris' => array_map(fn (array $b): array => [
                'UuidProduk' => $b['UuidProduk'],
                'NamaProduk' => $b['NamaProduk'],
                'Sku' => $b['Sku'],
                'SimbolSatuan' => $b['SimbolSatuan'],
                'BolehDesimal' => $b['BolehDesimal'],
                'Pelacakan' => $b['Pelacakan'],
                'Arah' => str_starts_with((string) $b['Jumlah'], '-') ? 'Keluar' : 'Masuk',
                'Jumlah' => ltrim((string) $b['Jumlah'], '-'),
                'HppSatuan' => $b['HppSatuan'],
                'UuidBatchStok' => $b['UuidBatchStok'],
                'NomorBatch' => $b['NomorBatch'],
                'TanggalKedaluwarsa' => $b['TanggalKedaluwarsa'],
                'UuidNomorSeri' => $b['UuidNomorSeri'],
                'NomorSeri' => $b['NomorSeri'],
            ], $this->AmbilBaris($p)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function AmbilBaris(PenyesuaianStok $p): array
    {
        $detail = PenyesuaianStokDetail::query()->where('IdPenyesuaianStok', $p->Id)->orderBy('Urutan')->get();
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($detail->pluck('IdProduk')->all())), true);
        $uuidBatch = $this->pelacakan->AmbilUuidBatch(array_values(array_filter($detail->pluck('IdBatchStok')->all(), 'is_int')));
        $uuidSeri = $this->pelacakan->AmbilUuidSeri(array_values(array_filter($detail->pluck('IdNomorSeri')->all(), 'is_int')));

        return array_values($detail->map(function (PenyesuaianStokDetail $d) use ($produk, $uuidBatch, $uuidSeri): array {
            $info = $produk[$d->IdProduk] ?? null;

            return [
                'Urutan' => $d->Urutan,
                'UuidProduk' => $info->uuid ?? '',
                'NamaProduk' => $d->NamaProduk,
                'Sku' => $d->Sku,
                'SimbolSatuan' => $info->simbolSatuan ?? '',
                'BolehDesimal' => $info->bolehDesimal ?? false,
                'Pelacakan' => $info?->pelacakan->value ?? 'Tidak',
                'Jumlah' => $d->Jumlah,
                'HppSatuan' => $d->HppSatuan,
                'Nilai' => $d->Nilai,
                'UuidBatchStok' => $d->IdBatchStok === null ? null : ($uuidBatch[$d->IdBatchStok] ?? null),
                'NomorBatch' => $d->NomorBatch,
                'TanggalKedaluwarsa' => $d->TanggalKedaluwarsa?->format('Y-m-d'),
                'UuidNomorSeri' => $d->IdNomorSeri === null ? null : ($uuidSeri[$d->IdNomorSeri] ?? null),
                'NomorSeri' => $d->NomorSeri,
            ];
        })->all());
    }
}
