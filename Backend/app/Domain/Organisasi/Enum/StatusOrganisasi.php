<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Status outlet & gudang (F-02). Data organisasi tidak pernah dihapus karena dirujuk transaksi & laporan;
 * yang tidak dipakai lagi diarsipkan dan bisa dipulihkan.
 */
enum StatusOrganisasi: string
{
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this !== $tujuan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
