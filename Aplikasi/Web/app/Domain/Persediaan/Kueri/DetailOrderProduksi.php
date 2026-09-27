<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusOrderProduksi;
use App\Domain\Persediaan\Model\OrderProduksi;
use App\Domain\Persediaan\Model\OrderProduksiBahan;

/**
 * Detail & isian formulir order produksi (F-05e): kepala, bahan (standar vs aktual, selisih, nilai), jurnal, dan
 * riwayat status. Jumlah dalam satuan dasar dengan simbol satuan produk.
 */
final class DetailOrderProduksi
{
    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
        private readonly JurnalSumber $jurnalSumber,
        private readonly RiwayatDokumenPersediaan $riwayat,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(OrderProduksi $o): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$o->IdGudang])[$o->IdGudang] ?? null;
        [$riwayat, $nama] = $this->riwayat->Ambil(OrderProduksi::JENIS_DOKUMEN, $o->Id, fn (string $s): string => StatusOrderProduksi::tryFrom($s)?->AmbilLabel() ?? $s, [$o->DibuatOleh, $o->DipostingOleh, $o->DibatalkanOleh]);
        $hasil = $this->infoProduk->AmbilBanyak([$o->IdProduk], denganTerhapus: true)[$o->IdProduk] ?? null;

        return [
            'Order' => [
                'Uuid' => $o->Uuid,
                'Nomor' => $o->Nomor,
                'Status' => $o->Status->value,
                'LabelStatus' => $o->Status->AmbilLabel(),
                'NamaGudang' => $gudang->nama ?? '',
                'NamaOutlet' => $gudang?->namaOutlet,
                'Tanggal' => $o->Tanggal->format('Y-m-d'),
                'NamaProduk' => $o->NamaProduk,
                'Sku' => $o->Sku,
                'SimbolSatuan' => $hasil->simbolSatuan ?? '',
                'JumlahHasil' => $o->JumlahHasil,
                'VersiResep' => $o->VersiResep,
                'BiayaOverhead' => $o->BiayaOverhead,
                'NomorBatch' => $o->NomorBatch,
                'TanggalKedaluwarsa' => $o->TanggalKedaluwarsa?->format('Y-m-d'),
                'Keterangan' => $o->Keterangan,
                'TotalNilaiBahan' => $o->TotalNilaiBahan,
                'NilaiHasil' => $o->NilaiHasil,
                'HppSatuanHasil' => $o->HppSatuanHasil,
                'AlasanBatal' => $o->AlasanBatal,
                'DibuatOleh' => $nama($o->DibuatOleh),
                'DipostingOleh' => $nama($o->DipostingOleh),
                'DipostingPada' => $o->DipostingPada?->toIso8601String(),
                'DibatalkanOleh' => $nama($o->DibatalkanOleh),
                'DibatalkanPada' => $o->DibatalkanPada?->toIso8601String(),
            ],
            'Bahan' => $this->AmbilBahan($o),
            'Jurnal' => $this->jurnalSumber->Ambil(JenisSumberJurnal::OrderProduksi, $o->Id),
            'Riwayat' => $riwayat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilForm(OrderProduksi $o): array
    {
        $hasil = $this->infoProduk->AmbilBanyak([$o->IdProduk], denganTerhapus: true)[$o->IdProduk] ?? null;
        $gudang = $this->infoGudang->AmbilBanyak([$o->IdGudang])[$o->IdGudang] ?? null;

        return [
            'Uuid' => $o->Uuid,
            'UuidGudang' => $gudang->uuid ?? '',
            'Tanggal' => $o->Tanggal->format('Y-m-d'),
            'Produk' => $hasil === null ? null : [
                'Uuid' => $hasil->uuid,
                'Nama' => $hasil->nama,
                'Sku' => $hasil->sku,
                'SimbolSatuan' => $hasil->simbolSatuan,
                'BolehDesimal' => $hasil->bolehDesimal,
                'Pelacakan' => $hasil->pelacakan->value,
            ],
            'JumlahHasil' => Kuantitas::Dari($o->JumlahHasil)->KeDesimal()->strippedOfTrailingZeros()->__toString(),
            'BiayaOverhead' => $o->BiayaOverhead,
            'NomorBatch' => $o->NomorBatch,
            'TanggalKedaluwarsa' => $o->TanggalKedaluwarsa?->format('Y-m-d'),
            'Keterangan' => $o->Keterangan,
            'VersiDiubahPada' => $o->DiubahPada?->toIso8601String() ?? '',
            'Bahan' => array_map(fn (array $b): array => [
                'UuidProduk' => $b['UuidProduk'],
                'NamaProduk' => $b['NamaProduk'],
                'Sku' => $b['Sku'],
                'SimbolSatuan' => $b['SimbolSatuan'],
                'BolehDesimal' => $b['BolehDesimal'],
                'JumlahStandar' => $b['JumlahStandar'],
                'Jumlah' => Kuantitas::Dari($b['Jumlah'])->KeDesimal()->strippedOfTrailingZeros()->__toString(),
            ], $this->AmbilBahan($o)),
        ];
    }

    /**
     * @return list<array{UuidProduk: string, NamaProduk: string, Sku: string|null, SimbolSatuan: string, BolehDesimal: bool, JumlahStandar: string, Jumlah: string, Selisih: string, Nilai: string|null}>
     */
    private function AmbilBahan(OrderProduksi $o): array
    {
        $baris = OrderProduksiBahan::query()->where('IdOrderProduksi', $o->Id)->orderBy('Urutan')->get();
        $info = $this->infoProduk->AmbilBanyak(array_values(array_unique($baris->pluck('IdProduk')->all())), denganTerhapus: true);

        return array_values($baris->map(fn (OrderProduksiBahan $b): array => [
            'UuidProduk' => $info[$b->IdProduk]->uuid ?? '',
            'NamaProduk' => $b->NamaProduk,
            'Sku' => $b->Sku,
            'SimbolSatuan' => $info[$b->IdProduk]->simbolSatuan ?? '',
            'BolehDesimal' => $info[$b->IdProduk]->bolehDesimal ?? true,
            'JumlahStandar' => $b->JumlahStandar,
            'Jumlah' => $b->Jumlah,
            'Selisih' => Kuantitas::Dari($b->Jumlah)->Kurangi(Kuantitas::Dari($b->JumlahStandar))->KeString(),
            'Nilai' => $b->Nilai,
        ])->all());
    }
}
