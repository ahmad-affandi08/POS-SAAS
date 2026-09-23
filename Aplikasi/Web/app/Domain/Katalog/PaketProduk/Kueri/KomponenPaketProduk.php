<?php

declare(strict_types=1);

namespace App\Domain\Katalog\PaketProduk\Kueri;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Katalog\PaketProduk\Model\PaketProdukDetail;

/**
 * Komponen produk paket untuk halaman `Kelola/Produk/Komponen` (prop `Komponen`, F-03 E.9). `AlokasiHarga` "" =
 * otomatis.
 */
final class KomponenPaketProduk
{
    /**
     * @return list<array{UuidProdukKomponen: string, Nama: string, Sku: string|null, Jumlah: string, SimbolSatuan: string, AlokasiHarga: string}>
     */
    public function Ambil(Produk $produk): array
    {
        $detail = PaketProdukDetail::query()->with('ProdukKomponen:Id,Uuid,Nama,Sku,IdSatuanDasar')->where('IdProdukPaket', $produk->Id)->orderBy('Urutan')->get();
        $simbol = Satuan::query()->whereKey($detail->map(fn (PaketProdukDetail $baris): int => $baris->ProdukKomponen->IdSatuanDasar)->unique()->values()->all())->pluck('Simbol', 'Id');

        return array_values($detail->map(fn (PaketProdukDetail $baris): array => [
            'UuidProdukKomponen' => $baris->ProdukKomponen->Uuid,
            'Nama' => $baris->ProdukKomponen->Nama,
            'Sku' => $baris->ProdukKomponen->Sku,
            'Jumlah' => $baris->Jumlah,
            'SimbolSatuan' => (string) $simbol->get($baris->ProdukKomponen->IdSatuanDasar, ''),
            'AlokasiHarga' => $baris->AlokasiHarga ?? '',
        ])->all());
    }
}
