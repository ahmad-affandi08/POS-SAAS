<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatPenjualan;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-07b penjualan: mendaftarkan penangan item outbox POS `Penjualan.Buat` ke `PemrosesSinkron`. Rute
 * didaftarkan dari routes/Pos.php dan routes/Penjualan.php.
 */
final class PenyediaPenjualan extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PenanganSinkronBuatPenjualan::class], PenanganItemSinkron::TAG);
    }
}
