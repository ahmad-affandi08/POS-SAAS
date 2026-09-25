<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Persediaan\Enum\AlasanPenyesuaian;
use Carbon\CarbonImmutable;

/**
 * Masukan `SimpanPenyesuaianStok` (F-05b): draf penyesuaian satu lokasi stok dengan alasan wajib.
 */
final readonly class DataPenyesuaianStok
{
    /**
     * @param  list<DataBarisDokumenStok>  $baris
     */
    public function __construct(
        public ?string $uuid,
        public int $idGudang,
        public CarbonImmutable $tanggal,
        public AlasanPenyesuaian $alasan,
        public ?string $keterangan,
        public array $baris,
        public ?string $versiDiubahPada = null,
    ) {}
}
