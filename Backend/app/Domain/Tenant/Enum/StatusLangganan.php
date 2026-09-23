<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * State machine `Langganan.Status` (F-00, BR-00.7): Trial → Aktif → Tertunggak → Ditangguhkan → Berhenti, cabang Gratis.
 */
enum StatusLangganan: string
{
    case Trial = 'Trial';
    case Aktif = 'Aktif';
    case Tertunggak = 'Tertunggak';
    case Ditangguhkan = 'Ditangguhkan';
    case Berhenti = 'Berhenti';
    case Gratis = 'Gratis';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Trial => [self::Aktif, self::Gratis],
            self::Aktif => [self::Tertunggak, self::Berhenti],
            self::Tertunggak => [self::Aktif, self::Ditangguhkan],
            self::Ditangguhkan => [self::Aktif, self::Gratis, self::Berhenti],
            self::Gratis => [self::Aktif],
            self::Berhenti => [],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Aktif => 'Aktif',
            self::Tertunggak => 'Tertunggak',
            self::Ditangguhkan => 'Ditangguhkan',
            self::Berhenti => 'Berhenti',
            self::Gratis => 'Gratis',
        };
    }
}
