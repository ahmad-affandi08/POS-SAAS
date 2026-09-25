<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Penjualan\Kueri\PemakaianAkunDiMetodePembayaran;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatPenjualan;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatReturPenjualan;
use App\Domain\Penjualan\Layanan\PenanganSinkronVoidPenjualan;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-07b/F-09 penjualan: mendaftarkan penangan item outbox POS `Penjualan.Buat`, `Penjualan.Void`, dan
 * `ReturPenjualan.Buat` ke `PemrosesSinkron`. Rute
 * didaftarkan dari routes/Pos.php dan routes/Penjualan.php.
 */
final class PenyediaPenjualan extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            PenanganSinkronBuatPenjualan::class,
            PenanganSinkronVoidPenjualan::class,
            PenanganSinkronBuatReturPenjualan::class,
        ], PenanganItemSinkron::TAG);
        // F-13a: akun yang dirujuk metode pembayaran tidak bisa dihapus dari bagan akun.
        $this->app->tag(PemakaianAkunDiMetodePembayaran::class, PemeriksaPemakaianAkun::TAG);
    }
}
