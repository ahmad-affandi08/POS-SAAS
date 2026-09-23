<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 1 Katalog Inti: produk, kategori, satuan, varian, barcode, gambar, batas stok.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: pemeriksa pemakaian varian dan bagian katalog POS Tim 1.
 */
final class PenyediaKatalog extends ServiceProvider
{
    public function register(): void
    {
        // Diisi tim pemilik pada Wave 1/2 (ikatan kontrak Katalog\Kontrak).
    }

    public function boot(): void
    {
        // Diisi tim pemilik pada Wave 1/2 (tag PemeriksaPemakaianProduk::TAG dan BagianKatalogPos::TAG).
    }
}
