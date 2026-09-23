<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 2 Harga & Pajak: harga, daftar harga, riwayat harga, penentu harga, kelompok pajak, katalog POS.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: bagian katalog POS Tim 2 bila perlu.
 */
final class PenyediaKatalogHarga extends ServiceProvider
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
