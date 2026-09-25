<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris refund item outbox `ReturPenjualan.Buat` (F-09 fase 1): metode jenis Tunai atau Transfer.
 */
final readonly class DataRefundReturPenjualanPos
{
    public function __construct(
        public string $uuid,
        public string $uuidMetodePembayaran,
        public Uang $jumlah,
    ) {}
}
