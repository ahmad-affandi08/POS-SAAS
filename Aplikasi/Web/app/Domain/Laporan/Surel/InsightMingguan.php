<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Insight mingguan X6 ke pemilik (v3.79): omzet minggu lalu vs minggu sebelumnya, produk terlaris/naik/turun, stok yang
 * segera habis, dan pengingat Lebaran. Angka uang sudah diformat rupiah oleh pengirim. Berhenti berlangganan di halaman
 * Laporan penjualan.
 */
final class InsightMingguan extends SurelDasar
{
    /**
     * @param  array<string, mixed>  $insight
     */
    public function __construct(
        public readonly string $nama,
        public readonly string $namaUsaha,
        public readonly string $periode,
        public readonly array $insight,
        public readonly string $tautanLaporan,
        public readonly string $tautanRestock,
    ) {
        $this->subject("{$namaUsaha}: insight penjualan {$periode}")
            ->IsiSurel('Tenant.InsightMingguan', [
                'Nama' => $nama,
                'NamaUsaha' => $namaUsaha,
                'Periode' => $periode,
                'Insight' => $insight,
                'TautanLaporan' => $tautanLaporan,
                'TautanRestock' => $tautanRestock,
            ]);
    }
}
