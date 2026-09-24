<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Layanan;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Konversi waktu daftar harga antara teks form `YYYY-MM-DDTHH:mm` di zona waktu tenant dan UTC yang disimpan
 * (DesainF03 E.7, H.22). Teks kosong = tanpa batas (null).
 */
final class WaktuLokalDaftarHarga
{
    public const FORMAT = 'Y-m-d\TH:i';

    public static function KeTeks(?DateTimeInterface $waktu, string $zonaWaktu): string
    {
        return $waktu === null ? '' : CarbonImmutable::instance($waktu)->setTimezone($zonaWaktu)->format(self::FORMAT);
    }

    public static function DariTeks(?string $teks, string $zonaWaktu): ?CarbonImmutable
    {
        if ($teks === null || trim($teks) === '') {
            return null;
        }

        $waktu = CarbonImmutable::createFromFormat(self::FORMAT, trim($teks), $zonaWaktu);

        return $waktu === null ? null : $waktu->startOfMinute()->utc();
    }
}
