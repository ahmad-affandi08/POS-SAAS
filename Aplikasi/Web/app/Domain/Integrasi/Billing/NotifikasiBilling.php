<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;

/**
 * Notifikasi webhook gerbang billing platform yang tanda tangannya sudah terbukti sah.
 */
final class NotifikasiBilling
{
    public function __construct(
        public readonly string $nomorPesanan,
        public readonly int $idTenant,
        public readonly string $uuidPembayaran,
        public readonly StatusPembayaranGerbang $status,
        public readonly string $jumlah,
        public readonly string $idTransaksi,
        public readonly string $statusAsli,
    ) {}
}
