<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Katalog\Harga\Kueri\HargaPajakUntukPos;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 2 Harga & Pajak: harga, daftar harga, riwayat harga, penentu harga, kelompok pajak, katalog POS.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: bagian katalog POS Tim 2.
 */
final class PenyediaKatalogHarga extends ServiceProvider
{
    public function register(): void
    {
        // D.3: bagian KelompokPajak, DaftarHarga, ProdukHarga katalog POS.
        $this->app->tag([HargaPajakUntukPos::class], BagianKatalogPos::TAG);
    }

    public function boot(): void
    {
        // Tidak ada yang perlu di-boot.
    }
}
