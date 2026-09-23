<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Enum;

/**
 * Alert otomatis dasbor operasional (P-11, BR-P11.1, §14.5). Nilai = kolom `AlertOperasional.Kunci`.
 */
enum JenisAlertOperasional: string
{
    case PenjadwalBerhenti = 'PenjadwalBerhenti';
    case AntreanTertunda = 'AntreanTertunda';
    case BackupTerlambat = 'BackupTerlambat';

    public function AmbilTingkat(): TingkatAlert
    {
        return match ($this) {
            self::PenjadwalBerhenti, self::AntreanTertunda => TingkatAlert::Kritis,
            self::BackupTerlambat => TingkatAlert::Peringatan,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PenjadwalBerhenti => 'Scheduler berhenti',
            self::AntreanTertunda => 'Antrean tertunda',
            self::BackupTerlambat => 'Backup terlambat',
        };
    }
}
