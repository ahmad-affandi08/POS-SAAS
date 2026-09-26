<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/**
 * Status reservasi layanan (F-07 mode service, §9.8): `Menunggu` (butuh konfirmasi toko) → `Dikonfirmasi` → `Hadir`
 * (check-in) → `Selesai` (dibayar di kasir); `Batal` dan `TidakDatang` adalah status akhir. Slot staf terpakai selama
 * Menunggu/Dikonfirmasi/Hadir.
 */
enum StatusReservasi: string
{
    case Menunggu = 'Menunggu';
    case Dikonfirmasi = 'Dikonfirmasi';
    case Hadir = 'Hadir';
    case Selesai = 'Selesai';
    case Batal = 'Batal';
    case TidakDatang = 'TidakDatang';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Menunggu => in_array($tujuan, [self::Dikonfirmasi, self::Hadir, self::Batal], true),
            self::Dikonfirmasi => in_array($tujuan, [self::Hadir, self::Batal, self::TidakDatang], true),
            self::Hadir => in_array($tujuan, [self::Selesai, self::Batal], true),
            self::Selesai, self::Batal, self::TidakDatang => false,
        };
    }

    public function CekMemakaiSlot(): bool
    {
        return in_array($this, [self::Menunggu, self::Dikonfirmasi, self::Hadir], true);
    }

    /** @return list<string> */
    public static function AmbilNilaiMemakaiSlot(): array
    {
        return [self::Menunggu->value, self::Dikonfirmasi->value, self::Hadir->value];
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu konfirmasi',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::Hadir => 'Sudah datang',
            self::Selesai => 'Selesai',
            self::Batal => 'Dibatalkan',
            self::TidakDatang => 'Tidak datang',
        };
    }
}
