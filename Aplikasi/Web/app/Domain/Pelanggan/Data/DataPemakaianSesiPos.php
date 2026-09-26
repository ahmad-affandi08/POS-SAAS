<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use Carbon\CarbonImmutable;

/**
 * Item outbox `Sesi.Pakai` (F-16d bagian 2) yang sudah divalidasi bentuknya: pemakaian sesi paket pelanggan di kasir
 * (bisa offline). `uuid` = Uuid dokumen dari perangkat.
 */
final readonly class DataPemakaianSesiPos
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidSaldoSesi,
        public string $uuidProduk,
        public int $jumlah,
        public string $uuidPengguna,
        public CarbonImmutable $dibuatPada,
    ) {}
}
