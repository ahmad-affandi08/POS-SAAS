<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\PenanganSinkronBukaShift;
use App\Domain\Kasir\Layanan\PenanganSinkronMutasiKas;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-06 shift & kas: mendaftarkan penangan item outbox POS (`Shift.Buka`, `MutasiKas.Catat`) ke
 * `PemrosesSinkron`. Rute didaftarkan dari routes/Pos.php dan routes/Kasir.php.
 */
final class PenyediaKasir extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PenanganSinkronBukaShift::class, PenanganSinkronMutasiKas::class], PenanganItemSinkron::TAG);
    }
}
