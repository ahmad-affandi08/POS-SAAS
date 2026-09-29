<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use Carbon\CarbonImmutable;

/**
 * Masukan pembuatan faktur penjualan grosir (F-12, §9.7, BR-12.4): surat jalan mana saja yang ditagihkan. Angkanya
 * **tidak dikirim klien** — seluruhnya dijumlahkan dari surat jalan yang ditautkan.
 */
final readonly class DataFakturPenjualan
{
    /**
     * @param  list<string>  $uuidSuratJalan
     */
    public function __construct(
        public array $uuidSuratJalan,
        public CarbonImmutable $tanggal,
        public ?string $nomorFakturPajak = null,
        public ?string $catatan = null,
    ) {}
}
