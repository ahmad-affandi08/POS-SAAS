<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `TerimaReturTanpaStrukPos` dari item outbox `ReturPenjualan.TanpaStruk` (K28). `uuid` = Uuid item = Uuid
 * `ReturPenjualan`; `uuidPelanggan` wajib bila refund ke deposit; `totalRefund` = `Ringkasan.TotalRefund` hitungan
 * perangkat.
 */
final readonly class DataReturTanpaStrukPos
{
    /**
     * @param  list<DataBarisReturTanpaStrukPos>  $baris
     * @param  list<DataRefundReturPenjualanPos>  $refund
     */
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidShift,
        public string $uuidPengguna,
        public string $uuidPenyetuju,
        public ?string $uuidPelanggan,
        public string $nomor,
        public string $alasan,
        public CarbonImmutable $dibuatPada,
        public array $baris,
        public array $refund,
        public Uang $totalRefund,
    ) {}
}
