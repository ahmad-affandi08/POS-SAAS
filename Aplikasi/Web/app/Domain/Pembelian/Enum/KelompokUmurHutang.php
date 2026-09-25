<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/**
 * Kelompok umur hutang (F-04 fase 1, daftar hutang & jatuh tempo): hari lewat jatuh tempo pada tanggal laporan.
 * `BelumJatuhTempo` = jatuh tempo setelah tanggal laporan; lalu 0–30 (jatuh tempo hari ini termasuk 0), 31–60,
 * 61–90, > 90 hari lewat jatuh tempo (PRD "Rincian F-04 fase 1").
 */
enum KelompokUmurHutang: string
{
    case BelumJatuhTempo = 'BelumJatuhTempo';
    case Hari0Sampai30 = 'Hari0Sampai30';
    case Hari31Sampai60 = 'Hari31Sampai60';
    case Hari61Sampai90 = 'Hari61Sampai90';
    case LebihDari90 = 'LebihDari90';

    public static function DariHariLewat(int $hari): self
    {
        return match (true) {
            $hari < 0 => self::BelumJatuhTempo,
            $hari <= 30 => self::Hari0Sampai30,
            $hari <= 60 => self::Hari31Sampai60,
            $hari <= 90 => self::Hari61Sampai90,
            default => self::LebihDari90,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumJatuhTempo => 'Belum jatuh tempo',
            self::Hari0Sampai30 => '0–30 hari',
            self::Hari31Sampai60 => '31–60 hari',
            self::Hari61Sampai90 => '61–90 hari',
            self::LebihDari90 => '> 90 hari',
        };
    }
}
