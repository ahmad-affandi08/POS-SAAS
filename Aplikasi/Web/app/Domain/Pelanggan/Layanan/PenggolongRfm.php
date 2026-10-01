<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Pelanggan\Enum\SegmenRfm;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Penggolong segmen RFM CRM-07 (fungsi murni, tanpa float). Aturan dicek berurutan:
 * belum ada transaksi → BelumBelanja; terakhir > 180 hari → Hilang; > 60 hari → Berisiko; ≤ 30 hari dan (≥ 6
 * transaksi setahun atau belanja setahun ≥ batas 20% teratas) → Juara; ≥ 3 transaksi setahun → Setia; baru satu
 * transaksi seumur hidup → Baru; sisanya Potensial.
 */
final class PenggolongRfm
{
    public const HARI_PERIODE = 365;

    /**
     * Batas belanja 20% teratas (persentil 80) dari total belanja periode yang > 0; null bila pembelinya < 5 orang
     * (terlalu sedikit untuk dibandingkan).
     *
     * @param  list<string>  $total
     */
    public static function HitungBatasJuara(array $total): ?string
    {
        $total = array_values(array_filter($total, fn (string $t): bool => BigDecimal::of($t)->isPositive()));

        if (count($total) < 5) {
            return null;
        }

        usort($total, fn (string $a, string $b): int => BigDecimal::of($a)->compareTo($b));

        return $total[intdiv(4 * (count($total) - 1), 5)];
    }

    /**
     * @param  array{TanggalTerakhir: string, JumlahSeluruhnya: int, JumlahPeriode: int, TotalPeriode: string}|null  $bahan
     */
    public static function Golongkan(?array $bahan, CarbonImmutable $hariIni, ?string $batasJuara): SegmenRfm
    {
        if ($bahan === null || $bahan['JumlahSeluruhnya'] === 0) {
            return SegmenRfm::BelumBelanja;
        }

        $hari = (int) CarbonImmutable::parse($bahan['TanggalTerakhir'])->startOfDay()->diffInDays($hariIni->startOfDay());

        if ($hari > 180) {
            return SegmenRfm::Hilang;
        }

        if ($hari > 60) {
            return SegmenRfm::Berisiko;
        }

        $besar = $batasJuara !== null && BigDecimal::of($bahan['TotalPeriode'])->compareTo($batasJuara) >= 0;

        if ($hari <= 30 && ($bahan['JumlahPeriode'] >= 6 || $besar)) {
            return SegmenRfm::Juara;
        }

        if ($bahan['JumlahPeriode'] >= 3) {
            return SegmenRfm::Setia;
        }

        return $bahan['JumlahSeluruhnya'] === 1 ? SegmenRfm::Baru : SegmenRfm::Potensial;
    }
}
