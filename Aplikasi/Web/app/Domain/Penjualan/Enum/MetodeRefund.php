<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Ringkasan cara refund retur penjualan fase 1 (PRD "Rincian F-09 fase 1"): tunai dari laci shift aktif, transfer
 * manual (BR-09.2), atau keduanya. Tukar barang, nota kredit/saldo, dan refund gateway menyusul.
 */
enum MetodeRefund: string
{
    case Tunai = 'Tunai';
    case Transfer = 'Transfer';
    case Campuran = 'Campuran';
    // F-12: retur penjualan tempo mengurangi sisa piutang lebih dulu.
    case Piutang = 'Piutang';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::Transfer => 'Transfer manual',
            self::Campuran => 'Tunai & transfer',
            self::Piutang => 'Potong piutang',
        };
    }
}
