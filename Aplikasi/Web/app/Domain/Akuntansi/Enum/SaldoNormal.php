<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

enum SaldoNormal: string
{
    case Debit = 'Debit';
    case Kredit = 'Kredit';

    public function AmbilKebalikan(): self
    {
        return $this === self::Debit ? self::Kredit : self::Debit;
    }
}
