<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Daftar harga dalam bentuk yang dibaca `PenentuHarga` (tanpa model/DB). Kondisi null = berlaku untuk semua.
 * `uuidOutlet` = daftar Uuid outlet; `mulaiPada`/`selesaiPada` UTC setengah terbuka `[mulai, selesai)`.
 */
final readonly class DataDaftarHargaResolusi
{
    /**
     * @param  list<string>|null  $uuidOutlet
     */
    public function __construct(
        public string $uuid,
        public bool $aktif,
        public ?array $uuidOutlet,
        public ?KanalPenjualan $kanal,
        public ?string $tierPelanggan,
        public ?CarbonImmutable $mulaiPada,
        public ?CarbonImmutable $selesaiPada,
        public int $prioritas,
    ) {}
}
