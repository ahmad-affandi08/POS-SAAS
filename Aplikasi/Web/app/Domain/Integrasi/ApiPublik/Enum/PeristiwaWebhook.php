<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Enum;

/** X7 bagian 2 (PRD §16.4): peristiwa webhook keluar yang sudah tersedia. Sisa daftar §16.4 menyusul. */
enum PeristiwaWebhook: string
{
    case PenjualanSelesai = 'penjualan.selesai';
    case PenjualanDivoid = 'penjualan.divoid';
    case PenjualanDiretur = 'penjualan.diretur';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PenjualanSelesai => 'Penjualan selesai (diterima server)',
            self::PenjualanDivoid => 'Penjualan di-void',
            self::PenjualanDiretur => 'Retur penjualan diterima',
        };
    }

    /** @return list<string> */
    public static function AmbilSemuaNilai(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
