<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Data;

use Carbon\CarbonImmutable;

/**
 * Periode laporan (tanggal bisnis inklusif) hasil pembacaan saring `dari`/`sampai` (F-14a). `peringatan` berisi pesan
 * bila masukan disesuaikan (tanggal tidak sah, terbalik, atau melebihi batas hari laporan per baris).
 */
final readonly class DataPeriodeLaporan
{
    /** Batas panjang periode laporan per baris (PRD "Rincian F-14a"). */
    public const MAKS_HARI = 92;

    public function __construct(
        public CarbonImmutable $dari,
        public CarbonImmutable $sampai,
        public ?string $peringatan = null,
    ) {}

    /**
     * `dari`/`sampai` `YYYY-MM-DD`; kosong = `bawaanHari` hari terakhir sampai hari ini. Periode lebih panjang dari
     * `maksHari` dipotong dari tanggal `dari`.
     */
    public static function Baca(mixed $dari, mixed $sampai, CarbonImmutable $hariIni, int $bawaanHari = 7, int $maksHari = self::MAKS_HARI): self
    {
        $hariIni = $hariIni->startOfDay();
        $tanggalSampai = self::Urai($sampai) ?? $hariIni;
        $tanggalDari = self::Urai($dari) ?? $tanggalSampai->subDays($bawaanHari - 1);
        $peringatan = null;

        if ($tanggalDari->greaterThan($tanggalSampai)) {
            [$tanggalDari, $tanggalSampai] = [$tanggalSampai, $tanggalDari];
        }

        if ((int) $tanggalDari->diffInDays($tanggalSampai, true) + 1 > $maksHari) {
            $tanggalSampai = $tanggalDari->addDays($maksHari - 1);
            $peringatan = "Periode laporan paling panjang {$maksHari} hari. Laporan ditampilkan sampai {$tanggalSampai->translatedFormat('j F Y')}.";
        }

        return new self($tanggalDari, $tanggalSampai, $peringatan);
    }

    public static function Urai(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
            return null;
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal instanceof CarbonImmutable && $tanggal->format('Y-m-d') === $nilai ? $tanggal : null;
    }
}
