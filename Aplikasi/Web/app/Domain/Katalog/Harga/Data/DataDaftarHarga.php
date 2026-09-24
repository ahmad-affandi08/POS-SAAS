<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Isi form daftar harga (F-03 E.7). Kondisi null = berlaku untuk semua: `idOutlet` (daftar `Outlet.Id` tenant),
 * `kanal`, `tierPelanggan`. `mulaiPada`/`selesaiPada` sudah dalam UTC (rentang setengah terbuka `[mulai, selesai)`).
 */
final readonly class DataDaftarHarga
{
    /**
     * @param  list<int>|null  $idOutlet
     */
    public function __construct(
        public string $nama,
        public ?array $idOutlet,
        public ?KanalPenjualan $kanal,
        public ?string $tierPelanggan,
        public ?CarbonImmutable $mulaiPada,
        public ?CarbonImmutable $selesaiPada,
        public int $prioritas,
    ) {}
}
