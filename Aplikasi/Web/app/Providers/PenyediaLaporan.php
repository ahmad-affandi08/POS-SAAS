<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Laporan\Penangan\PerbaruiRingkasanPenjualanHarian;
use App\Domain\Penjualan\Peristiwa\PenjualanDiterima;
use App\Domain\Penjualan\Peristiwa\PenjualanDivoid;
use App\Domain\Penjualan\Peristiwa\ReturPenjualanDiterima;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-14a laporan: penangan antrean yang menghitung ulang `RingkasanPenjualanHarian` setelah penjualan, void,
 * atau retur diterima. Rute didaftarkan dari routes/Laporan.php.
 */
final class PenyediaLaporan extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([PenjualanDiterima::class, PenjualanDivoid::class, ReturPenjualanDiterima::class] as $peristiwa) {
            Event::listen($peristiwa, PerbaruiRingkasanPenjualanHarian::class);
        }
    }
}
