<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Jenis komisi (F-18): persen dari dasar komisi baris, atau nominal tetap × jumlah baris. */
enum JenisKomisi: string
{
    case Persen = 'Persen';
    case Tetap = 'Tetap';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Persen => 'Persen',
            self::Tetap => 'Nominal per jumlah',
        };
    }
}
