<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Model\Produk;

/**
 * Kepala halaman produk (tipe FE `KepalaProduk`, F-03 E.1) untuk semua halaman tab produk: identitas, status,
 * gambar kecil, induk varian, dan tab yang berlaku menurut jenis. Tautan ditulis sebagai string (bukan `route()`)
 * agar tab tim lain tidak bergantung pada urutan pendaftaran rute.
 */
final class KepalaProduk
{
    /**
     * @return array{Uuid: string, Nama: string, Sku: string|null, Jenis: string, LabelJenis: string, Status: string, UrlGambarKecil: string|null, UuidInduk: string|null, NamaInduk: string|null, Tab: list<array{Kunci: string, Label: string, Tautan: string}>}
     */
    public function Ambil(Produk $produk): array
    {
        $induk = $produk->IdInduk === null ? null : Produk::query()->withTrashed()->find($produk->IdInduk, ['Id', 'Uuid', 'Nama']);
        $dasar = '/kelola/produk/'.$produk->Uuid;
        $tab = [['Kunci' => 'Ringkasan', 'Label' => 'Ringkasan', 'Tautan' => $dasar]];

        if ($produk->Jenis->CekBisaDijual()) {
            $tab[] = ['Kunci' => 'Harga', 'Label' => 'Harga', 'Tautan' => $dasar.'/harga'];
        }

        if ($produk->Jenis->CekBolehPilihan() && $produk->IdInduk === null) {
            $tab[] = ['Kunci' => 'Pilihan', 'Label' => 'Pilihan', 'Tautan' => $dasar.'/pilihan'];
        }

        if ($produk->Jenis->CekBolehResep()) {
            $tab[] = ['Kunci' => 'Resep', 'Label' => 'Resep', 'Tautan' => $dasar.'/resep'];
        }

        if ($produk->Jenis->CekBolehKomponen()) {
            $tab[] = ['Kunci' => 'Komponen', 'Label' => 'Komponen paket', 'Tautan' => $dasar.'/komponen'];
        }

        return [
            'Uuid' => $produk->Uuid,
            'Nama' => $produk->Nama,
            'Sku' => $produk->Sku,
            'Jenis' => $produk->Jenis->value,
            'LabelJenis' => $produk->Jenis->AmbilLabel(),
            'Status' => $produk->AmbilStatus()->value,
            'UrlGambarKecil' => PenyimpanGambarProduk::BuatUrl($produk, 'kecil'),
            'UuidInduk' => $induk?->Uuid,
            'NamaInduk' => $induk?->Nama,
            'Tab' => $tab,
        ];
    }
}
