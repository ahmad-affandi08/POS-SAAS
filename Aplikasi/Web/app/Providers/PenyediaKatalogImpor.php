<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 4 Impor/Ekspor: impor dan ekspor produk Excel/CSV.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: pendaftaran tugas antrean impor bila perlu.
 */
final class PenyediaKatalogImpor extends ServiceProvider
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
