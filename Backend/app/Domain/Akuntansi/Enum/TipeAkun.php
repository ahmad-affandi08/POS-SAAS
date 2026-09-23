<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Tipe akun COA (PRD §11.2). Digit pertama kode akun menandai tipenya (1-xxxx Aset … 6-xxxx Beban).
 */
enum TipeAkun: string
{
    case Aset = 'Aset';
    case Kewajiban = 'Kewajiban';
    case Ekuitas = 'Ekuitas';
    case Pendapatan = 'Pendapatan';
    case Hpp = 'Hpp';
    case Beban = 'Beban';

    public function AmbilDigitAwalKode(): string
    {
        return match ($this) {
            self::Aset => '1',
            self::Kewajiban => '2',
            self::Ekuitas => '3',
            self::Pendapatan => '4',
            self::Hpp => '5',
            self::Beban => '6',
        };
    }

    /** Saldo normal akun biasa; akun kontra memakai kebalikannya. */
    public function AmbilSaldoNormal(bool $kontra = false): SaldoNormal
    {
        $normal = match ($this) {
            self::Aset, self::Hpp, self::Beban => SaldoNormal::Debit,
            self::Kewajiban, self::Ekuitas, self::Pendapatan => SaldoNormal::Kredit,
        };

        return $kontra ? $normal->AmbilKebalikan() : $normal;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aset => 'Aset',
            self::Kewajiban => 'Kewajiban',
            self::Ekuitas => 'Ekuitas',
            self::Pendapatan => 'Pendapatan',
            self::Hpp => 'HPP',
            self::Beban => 'Beban',
        };
    }
}
