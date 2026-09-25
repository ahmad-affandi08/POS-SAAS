<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `TerimaReturPenjualanPos` dari item outbox `ReturPenjualan.Buat` (F-09 fase 1). `uuid` = Uuid item = Uuid
 * `ReturPenjualan`; `idPerangkat`/`idOutlet` = perangkat pengirim; `totalRefund` = `Ringkasan.TotalRefund` hitungan
 * perangkat.
 */
final readonly class DataReturPenjualanPos
{
    /**
     * @param  list<DataBarisReturPenjualanPos>  $baris
     * @param  list<DataRefundReturPenjualanPos>  $refund
     */
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidPenjualanAsal,
        public string $uuidShift,
        public string $uuidPengguna,
        public string $uuidPenyetuju,
        public string $nomor,
        public string $alasan,
        public CarbonImmutable $dibuatPada,
        public array $baris,
        public array $refund,
        public Uang $totalRefund,
    ) {}
}
