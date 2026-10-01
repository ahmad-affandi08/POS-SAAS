<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/** v3.42: dokumen asal sebuah giro. */
enum JenisSumberGiro: string
{
    case PembayaranPiutang = 'PembayaranPiutang';
    case PembayaranHutang = 'PembayaranHutang';

    public function BuatTautan(string $uuid): string
    {
        return $this === self::PembayaranPiutang ? '/kelola/piutang/pelunasan/'.$uuid : '/kelola/pembelian/pembayaran/'.$uuid;
    }
}
