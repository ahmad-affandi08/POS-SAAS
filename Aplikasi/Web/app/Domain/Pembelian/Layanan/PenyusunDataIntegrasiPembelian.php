<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PenerimaanBarangDetail;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelianDetail;

/**
 * Data webhook X7 §16.4 untuk `pesanan-pembelian.disetujui` & `penerimaan-barang.diposting`: Uuid saja (tanpa Id
 * internal), uang & jumlah string desimal. Harga beli ikut (dokumen pembelian memang berisi harga pemasok), HPP
 * tidak.
 */
final class PenyusunDataIntegrasiPembelian
{
    public function __construct(
        private readonly InfoProdukStok $produk,
        private readonly InfoGudang $gudang,
    ) {}

    /** @return array<string, mixed> */
    public function Pesanan(PesananPembelian $po): array
    {
        $detail = PesananPembelianDetail::query()->where('IdPesananPembelian', $po->Id)->orderBy('Urutan')->get();
        $uuidProduk = $this->UuidProduk(array_values(array_map('intval', $detail->pluck('IdProduk')->all())));

        return [
            'Uuid' => $po->Uuid,
            'Nomor' => $po->Nomor,
            'UuidPemasok' => Pemasok::query()->whereKey($po->IdPemasok)->value('Uuid'),
            'UuidGudang' => $this->UuidGudang($po->IdGudang),
            'Tanggal' => $po->Tanggal->toDateString(),
            'PerkiraanTiba' => $po->PerkiraanTiba?->toDateString(),
            'Subtotal' => Uang::Dari($po->Subtotal)->KeString(),
            'Diskon' => Uang::Dari($po->Diskon)->KeString(),
            'Pajak' => Uang::Dari($po->Pajak)->KeString(),
            'Ongkir' => Uang::Dari($po->Ongkir)->KeString(),
            'Total' => Uang::Dari($po->Total)->KeString(),
            'Baris' => array_values($detail->map(fn (PesananPembelianDetail $d): array => [
                'UuidProduk' => $uuidProduk[$d->IdProduk] ?? null,
                'NamaProduk' => $d->NamaProduk,
                'Satuan' => $d->SimbolSatuan,
                'Jumlah' => Kuantitas::Dari($d->Jumlah)->KeString(),
                'Harga' => Uang::Dari($d->Harga)->KeString(),
            ])->all()),
        ];
    }

    /** @return array<string, mixed> */
    public function Penerimaan(PenerimaanBarang $grn): array
    {
        $detail = PenerimaanBarangDetail::query()->where('IdPenerimaanBarang', $grn->Id)->orderBy('Urutan')->get();
        $uuidProduk = $this->UuidProduk(array_values(array_map('intval', $detail->pluck('IdProduk')->all())));

        return [
            'Uuid' => $grn->Uuid,
            'Nomor' => $grn->Nomor,
            'UuidPesananPembelian' => $grn->IdPesananPembelian === null ? null : PesananPembelian::query()->whereKey($grn->IdPesananPembelian)->value('Uuid'),
            'UuidPemasok' => $grn->IdPemasok === null ? null : Pemasok::query()->whereKey($grn->IdPemasok)->value('Uuid'),
            'UuidGudang' => $this->UuidGudang($grn->IdGudang),
            'Tanggal' => $grn->Tanggal->toDateString(),
            'NomorSuratJalan' => $grn->NomorSuratJalan,
            'TotalNilai' => Uang::Dari($grn->TotalNilai)->KeString(),
            'Baris' => array_values($detail->map(fn (PenerimaanBarangDetail $d): array => [
                'UuidProduk' => $uuidProduk[$d->IdProduk] ?? null,
                'NamaProduk' => $d->NamaProduk,
                'Satuan' => $d->SimbolSatuan,
                'Jumlah' => Kuantitas::Dari($d->Jumlah)->KeString(),
                'NomorBatch' => $d->NomorBatch,
            ])->all()),
        ];
    }

    /**
     * @param  list<int>  $id
     * @return array<int, string>
     */
    private function UuidProduk(array $id): array
    {
        return array_map(fn ($p): string => $p->uuid, $this->produk->AmbilBanyak(array_values(array_unique($id)), denganTerhapus: true));
    }

    private function UuidGudang(int $id): ?string
    {
        return ($this->gudang->AmbilBanyak([$id])[$id] ?? null)?->uuid;
    }
}
