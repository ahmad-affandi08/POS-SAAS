<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 3 Modifier & Resep: kelompok pilihan, resep, paket produk.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: pemeriksa pemakaian komposisi, bagian katalog POS Tim 3, dan ikatan PenyediaHppBahan.
 */
final class PenyediaKatalogKomposisi extends ServiceProvider
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
