<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Jenis perangkat POS (PRD §15.3 `Perangkat.Jenis`, F-02 langkah 5). Huruf jenis membentuk kode perangkat untuk
 * penomoran offline `{KodeOutlet}-{Huruf}{NN}`, misal `JKT1-K02`. Salesman ditambahkan bersama flow-nya.
 */
enum JenisPerangkat: string
{
    case Kasir = 'Kasir';
    case Kds = 'Kds';
    case Gudang = 'Gudang';
    case Pelayan = 'Pelayan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Kasir => 'Kasir',
            self::Kds => 'Layar dapur (KDS)',
            self::Gudang => 'Gudang',
            self::Pelayan => 'Pelayan',
        };
    }

    public function AmbilHuruf(): string
    {
        return match ($this) {
            self::Kasir => 'K',
            self::Kds => 'D',
            self::Gudang => 'G',
            self::Pelayan => 'P',
        };
    }
}
