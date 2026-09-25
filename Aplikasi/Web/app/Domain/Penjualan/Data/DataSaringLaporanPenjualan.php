<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Saring laporan penjualan (F-14a): rentang tanggal bisnis (inklusif), outlet (null = semua outlet tenant; daftar
 * kosong = tidak ada outlet → hasil kosong), kasir (`Penjualan.IdPengguna`, untuk retur `ReturPenjualan.IdPengguna`),
 * dan kanal (untuk retur = kanal penjualan asal).
 */
final readonly class DataSaringLaporanPenjualan
{
    /**
     * @param  list<int>|null  $idOutlet
     */
    public function __construct(
        public CarbonImmutable $dari,
        public CarbonImmutable $sampai,
        public ?array $idOutlet = null,
        public ?int $idKasir = null,
        public ?KanalPenjualan $kanal = null,
    ) {}

    public function CekTanpaOutlet(): bool
    {
        return $this->idOutlet === [];
    }
}
