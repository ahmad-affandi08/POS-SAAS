<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Penjualan\Kueri\PemakaianAkunDiMetodePembayaran;
use App\Domain\Penjualan\Layanan\PenanganSinkronBatalkanBarisPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronBatalPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatPenjualan;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatPesananPenjualan;
use App\Domain\Penjualan\Layanan\PenanganSinkronBuatReturPenjualan;
use App\Domain\Penjualan\Layanan\PenanganSinkronBukaPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronKirimDapurPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronTambahPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronUbahPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenanganSinkronVoidPenjualan;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-07b/F-09 penjualan: mendaftarkan penangan item outbox POS `Penjualan.Buat`, `Penjualan.Void`,
 * `ReturPenjualan.Buat`, dan `PesananTerbuka.*` (F-07 mode meja) ke `PemrosesSinkron`. Rute
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
            // F-07 mode meja fase 1: pesanan terbuka.
            PenanganSinkronBukaPesananTerbuka::class,
            PenanganSinkronTambahPesananTerbuka::class,
            PenanganSinkronKirimDapurPesananTerbuka::class,
            PenanganSinkronBatalkanBarisPesananTerbuka::class,
            PenanganSinkronUbahPesananTerbuka::class,
            PenanganSinkronBatalPesananTerbuka::class,
            // F-12 bagian 2: pre-order + uang muka.
            PenanganSinkronBuatPesananPenjualan::class,
        ], PenanganItemSinkron::TAG);
        // F-13a: akun yang dirujuk metode pembayaran tidak bisa dihapus dari bagan akun.
        $this->app->tag(PemakaianAkunDiMetodePembayaran::class, PemeriksaPemakaianAkun::TAG);
    }
}
