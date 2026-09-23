<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Status;

/**
 * State machine data master bertanggal (P-02): Draf → MenungguTinjauan → Terbit; ditolak kembali ke Draf.
 * "Berakhir" tidak disimpan: dihitung dari BerlakuSampai yang sudah lewat.
 */
enum StatusDataMaster: string
{
    case Draf = 'Draf';
    case MenungguTinjauan = 'MenungguTinjauan';
    case Terbit = 'Terbit';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Draf => $tujuan === self::MenungguTinjauan,
            self::MenungguTinjauan => $tujuan === self::Terbit || $tujuan === self::Draf,
            self::Terbit => false,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::MenungguTinjauan => 'Menunggu tinjauan',
            self::Terbit => 'Terbit',
        };
    }
}
