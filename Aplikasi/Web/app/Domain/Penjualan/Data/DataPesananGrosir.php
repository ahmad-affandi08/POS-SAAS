<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use Carbon\CarbonImmutable;

/**
 * Permintaan simpan draf SO grosir (F-12, §9.7).
 */
final readonly class DataPesananGrosir
{
    /**
     * @param  list<DataBarisPesananGrosir>  $baris
     */
    public function __construct(
        public string $uuidPelanggan,
        public int $idOutlet,
        public CarbonImmutable $tanggal,
        public array $baris,
        public ?CarbonImmutable $tanggalKirimDiminta = null,
        public ?string $catatan = null,
    ) {}
}
