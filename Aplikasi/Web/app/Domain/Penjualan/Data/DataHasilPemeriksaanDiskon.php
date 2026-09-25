<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Organisasi\Data\DataAnggotaOutlet;

/**
 * Hasil `PemeriksaDiskonPenjualan` (BR-07.3, PRD v1.46): penyetuju yang disimpan (null bila tidak ada) dan alasan
 * tinjauan (`DiskonMelebihiBatas`, `IzinBerubah`) untuk diskon yang tidak lagi sesuai aturan saat diterima server.
 */
final readonly class DataHasilPemeriksaanDiskon
{
    /**
     * @param  array<string, string>  $tinjauan  kode alasan → alasan tinjauan (`Kode: keterangan`)
     */
    public function __construct(
        public ?DataAnggotaOutlet $penyetuju,
        public array $tinjauan,
    ) {}
}
