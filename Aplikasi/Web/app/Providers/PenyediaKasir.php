<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Kueri\PemakaianAkunDiKategoriKas;
use App\Domain\Kasir\Layanan\PenanganSinkronBukaLaci;
use App\Domain\Kasir\Layanan\PenanganSinkronBukaShift;
use App\Domain\Kasir\Layanan\PenanganSinkronMutasiKas;
use App\Domain\Kasir\Layanan\PenanganSinkronTutupShift;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-06 shift & kas: mendaftarkan penangan item outbox POS (`Shift.Buka`, `MutasiKas.Catat`, F-11
 * `Shift.Tutup`, cetak struk bagian 4 `Laci.Buka`) ke `PemrosesSinkron`. Rute didaftarkan dari routes/Pos.php dan routes/Kasir.php.
 */
final class PenyediaKasir extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PenanganSinkronBukaShift::class, PenanganSinkronBukaLaci::class, PenanganSinkronMutasiKas::class, PenanganSinkronTutupShift::class], PenanganItemSinkron::TAG);
        // F-13a: akun yang dirujuk kategori kas tidak bisa dihapus dari bagan akun.
        $this->app->tag(PemakaianAkunDiKategoriKas::class, PemeriksaPemakaianAkun::TAG);
    }
}
