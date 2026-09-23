<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Entitas katalog yang dihapus permanen dan dicatat di `PenghapusanKatalog` (bagian `Terhapus` katalog POS, F-03).
 * Nilai = nama tabel. Produk tidak ada di sini: produk di-soft delete dan dikirim dengan `Dihapus: true`.
 */
enum EntitasKatalog: string
{
    case Kategori = 'Kategori';
    case Satuan = 'Satuan';
    case ProdukSatuan = 'ProdukSatuan';
    case ProdukBarcode = 'ProdukBarcode';
    case ProdukHarga = 'ProdukHarga';
    case KelompokPilihan = 'KelompokPilihan';
    case Pilihan = 'Pilihan';
    case ProdukKelompokPilihan = 'ProdukKelompokPilihan';
    case PaketProdukDetail = 'PaketProdukDetail';
}
