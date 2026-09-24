<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu pembayaran item outbox `Penjualan.Buat` (F-07b). Tunai: `jumlah` = uang diterima.
 */
final readonly class DataPembayaranPenjualanPos
{
    public function __construct(
        public string $uuid,
        public string $uuidMetodePembayaran,
        public Uang $jumlah,
        public ?string $referensi,
    ) {}
}
