<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Item outbox `Deposit.Isi` (F-16d bagian 1) yang sudah divalidasi bentuknya: isi saldo deposit pelanggan di kasir
 * (bisa offline). `uuid` = Uuid dokumen dari perangkat.
 */
final readonly class DataIsiDepositPos
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public string $uuidShift,
        public string $uuidPengguna,
        public string $uuidPelanggan,
        public string $nomor,
        public Uang $jumlah,
        public string $uuidMetodePembayaran,
        public ?string $referensi,
        public CarbonImmutable $dibuatPada,
    ) {}
}
