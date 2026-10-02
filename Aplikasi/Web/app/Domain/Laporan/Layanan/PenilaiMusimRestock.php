<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Layanan;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * X6 faktor musiman saran restock (Ramadan/Lebaran & periode sama tahun lalu). Murni (tanpa basis data):
 * - **Pergeseran ke tahun lalu**: bila titik tengah periode cakupan jatuh di [Lebaran − 45 hari, Lebaran + 14 hari]
 *   (Ramadan ±30 hari + persiapan & arus balik), pergeseran = Lebaran tahun ini − Lebaran tahun lalu (kalender Hijriah
 *   maju ±11 hari per tahun), jenis `Lebaran`. Selain itu 364 hari (52 minggu, hari dalam pekan tetap sama), jenis
 *   `TahunLalu`. Tanggal Lebaran diambil dari master hari libur terbit (P-02).
 * - **Faktor** = (pemakaian periode cakupan tahun lalu ÷ hari cakupan) ÷ (pemakaian periode dasar tahun lalu ÷ hari
 *   dasar), dua desimal, dibatasi 0,50–3,00. Tanpa data tahun lalu (salah satu nol) = 1,00.
 */
final class PenilaiMusimRestock
{
    public const HARI_SEBELUM_LEBARAN = 45;

    public const HARI_SESUDAH_LEBARAN = 14;

    public const HARI_TAHUN_LALU = 364;

    public const FAKTOR_MINIMUM = '0.50';

    public const FAKTOR_MAKSIMUM = '3.00';

    /**
     * @param  array<int, CarbonImmutable>  $lebaranPerTahun  tahun → hari pertama Idul Fitri
     * @return array{Jenis: 'Lebaran'|'TahunLalu', SelisihHari: int, Lebaran: string|null}
     */
    public static function TentukanPergeseran(CarbonImmutable $mulai, int $hariCakupan, array $lebaranPerTahun): array
    {
        $tengah = $mulai->startOfDay()->addDays(intdiv($hariCakupan - 1, 2));

        foreach ($lebaranPerTahun as $tahun => $lebaran) {
            $lalu = $lebaranPerTahun[$tahun - 1] ?? null;

            if ($lalu === null) {
                continue;
            }

            $dari = $lebaran->startOfDay()->subDays(self::HARI_SEBELUM_LEBARAN);
            $sampai = $lebaran->startOfDay()->addDays(self::HARI_SESUDAH_LEBARAN);

            if ($tengah->betweenIncluded($dari, $sampai)) {
                return [
                    'Jenis' => 'Lebaran',
                    'SelisihHari' => (int) $lalu->startOfDay()->diffInDays($lebaran->startOfDay()),
                    'Lebaran' => $lebaran->toDateString(),
                ];
            }
        }

        return ['Jenis' => 'TahunLalu', 'SelisihHari' => self::HARI_TAHUN_LALU, 'Lebaran' => null];
    }

    public static function HitungFaktor(string $pakaiLaluCakupan, int $hariCakupan, string $pakaiLaluDasar, int $hariDasar): BigDecimal
    {
        $cakupan = BigDecimal::of($pakaiLaluCakupan);
        $dasar = BigDecimal::of($pakaiLaluDasar);

        if (! $cakupan->isPositive() || ! $dasar->isPositive()) {
            return BigDecimal::one()->toScale(2);
        }

        $faktor = $cakupan->multipliedBy($hariDasar)->dividedBy($dasar->multipliedBy($hariCakupan), 2, RoundingMode::HalfUp);

        return BigDecimal::max(BigDecimal::of(self::FAKTOR_MINIMUM), BigDecimal::min(BigDecimal::of(self::FAKTOR_MAKSIMUM), $faktor));
    }
}
