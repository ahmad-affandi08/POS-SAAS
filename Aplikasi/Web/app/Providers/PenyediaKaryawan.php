<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Karyawan\Aksi\CatatAbsensiKeluarPos;
use App\Domain\Karyawan\Aksi\CatatAbsensiMasukPos;
use App\Domain\Karyawan\Layanan\PenanganSinkronAbsensi;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-18 karyawan: mendaftarkan penangan item outbox POS `Absensi.Masuk` & `Absensi.Keluar`. Rute back-office
 * dari routes/Karyawan.php.
 */
final class PenyediaKaryawan extends ServiceProvider
{
    public function register(): void
    {
        foreach ([PenanganSinkronAbsensi::JENIS_MASUK, PenanganSinkronAbsensi::JENIS_KELUAR] as $jenis) {
            $this->app->bind("penangan-sinkron.{$jenis}", fn ($app): PenanganSinkronAbsensi => new PenanganSinkronAbsensi(
                $jenis,
                $app->make(CatatAbsensiMasukPos::class),
                $app->make(CatatAbsensiKeluarPos::class),
            ));
        }

        $this->app->tag(['penangan-sinkron.'.PenanganSinkronAbsensi::JENIS_MASUK, 'penangan-sinkron.'.PenanganSinkronAbsensi::JENIS_KELUAR], PenanganItemSinkron::TAG);
    }
}
