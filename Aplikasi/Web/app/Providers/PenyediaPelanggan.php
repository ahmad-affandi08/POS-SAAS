<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Pelanggan\Layanan\PenanganSinkronBuatPelanggan;
use App\Domain\Pelanggan\Layanan\PenanganSinkronPakaiSesi;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-16a pelanggan: mendaftarkan penangan item outbox POS `Pelanggan.Buat` dan `Sesi.Pakai` (F-16d bagian 2).
 * Rute back-office dari routes/Pelanggan.php, rute POS dari routes/Pos.php.
 */
final class PenyediaPelanggan extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PenanganSinkronBuatPelanggan::class, PenanganSinkronPakaiSesi::class], PenanganItemSinkron::TAG);
    }
}
