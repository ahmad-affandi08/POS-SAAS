<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

use Carbon\CarbonImmutable;

/**
 * D-23 D: frekuensi transaksi kas & bank berulang. Bulanan memakai tanggal acuan (tanggal 29–31 menjadi hari
 * terakhir bulan pendek, lalu kembali ke tanggal acuan di bulan berikutnya); mingguan tiap 7 hari.
 */
enum FrekuensiJadwalKasBank: string
{
    case Mingguan = 'Mingguan';
    case Bulanan = 'Bulanan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Mingguan => 'Tiap minggu',
            self::Bulanan => 'Tiap bulan',
        };
    }

    /** Jatuh tempo setelah `sekarang` mengikuti tanggal acuan. */
    public function HitungBerikutnya(CarbonImmutable $acuan, CarbonImmutable $sekarang): CarbonImmutable
    {
        if ($this === self::Mingguan) {
            return $sekarang->addDays(7);
        }

        $bulan = $sekarang->startOfMonth()->addMonthNoOverflow();

        return $bulan->setDay(min($acuan->day, $bulan->daysInMonth));
    }
}
