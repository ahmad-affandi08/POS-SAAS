<?php

declare(strict_types=1);

namespace App\Domain\Promo\Enum;

/**
 * Cara pemasok menyelesaikan klaim promo (F-16c bagian 4b & 4e): transfer/tunai ke akun kas/bank, atau dipotong dari
 * hutang tenant ke pemasok itu (kompensasi/nota debit).
 */
enum CaraPenerimaanKlaim: string
{
    case KasBank = 'KasBank';
    case PotongHutang = 'PotongHutang';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::KasBank => 'Diterima di kas/bank',
            self::PotongHutang => 'Potong hutang ke pemasok',
        };
    }
}
