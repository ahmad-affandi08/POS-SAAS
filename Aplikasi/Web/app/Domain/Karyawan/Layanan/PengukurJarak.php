<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Jarak dua titik GPS dalam meter untuk geofence absensi web (F-18 bagian 4, D-37).
 *
 * Proyeksi equirectangular: d = R × √(Δφ² + (Δλ · cos φrata)²). Untuk jarak sampai puluhan kilometer selisihnya dengan
 * haversine di bawah 0,1 %, jauh di bawah akurasi GPS ponsel, dan cukup untuk memilih outlet terdekat. Semua hitungan
 * memakai desimal presisi tetap (cosinus lewat deret Taylor), sesuai aturan tanpa bilangan pecahan biner di domain.
 */
final class PengukurJarak
{
    private const SKALA = 20;

    private const PI = '3.14159265358979323846264338327950288';

    /** Jari-jari bumi rata-rata (IUGG), meter. */
    private const JARI_JARI_BUMI = '6371008.8';

    public function HitungMeter(string $lintangA, string $bujurA, string $lintangB, string $bujurB): int
    {
        $phiA = self::KeRadian(BigDecimal::of($lintangA));
        $phiB = self::KeRadian(BigDecimal::of($lintangB));
        $selisihBujur = BigDecimal::of($bujurB)->minus(BigDecimal::of($bujurA));

        // Lewat garis bujur 180°: ambil jalan terpendek.
        if ($selisihBujur->isGreaterThan(180)) {
            $selisihBujur = $selisihBujur->minus(360);
        } elseif ($selisihBujur->isLessThan(-180)) {
            $selisihBujur = $selisihBujur->plus(360);
        }

        $dy = $phiB->minus($phiA);
        $phiRata = $phiA->plus($phiB)->dividedBy(2, self::SKALA, RoundingMode::HalfEven);
        $dx = self::KeRadian($selisihBujur)->multipliedBy(self::HitungKosinus($phiRata));
        $kuadrat = $dx->multipliedBy($dx)->plus($dy->multipliedBy($dy))->toScale(self::SKALA, RoundingMode::HalfEven);

        return $kuadrat->sqrt(self::SKALA, RoundingMode::HalfEven)
            ->multipliedBy(self::JARI_JARI_BUMI)
            ->toScale(0, RoundingMode::HalfUp)
            ->toBigInteger()
            ->toInt();
    }

    private static function KeRadian(BigDecimal $derajat): BigDecimal
    {
        return $derajat->multipliedBy(self::PI)->dividedBy(180, self::SKALA, RoundingMode::HalfEven);
    }

    /** cos x = Σ (−1)ⁿ x²ⁿ / (2n)!, |x| ≤ π/2; 15 suku sudah melampaui skala hitung. */
    private static function HitungKosinus(BigDecimal $x): BigDecimal
    {
        $kuadrat = $x->multipliedBy($x)->toScale(self::SKALA, RoundingMode::HalfEven);
        $suku = BigDecimal::one();
        $jumlah = BigDecimal::one();

        for ($n = 1; $n <= 15; $n++) {
            $suku = $suku->multipliedBy($kuadrat)->negated()->dividedBy((2 * $n - 1) * (2 * $n), self::SKALA, RoundingMode::HalfEven);
            $jumlah = $jumlah->plus($suku);
        }

        return $jumlah;
    }
}
