<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Enum\SumberPesananGrosir;
use Carbon\CarbonImmutable;

/**
 * Permintaan simpan draf SO grosir (F-12, §9.7). Modul Salesman: `uuid` = Uuid dari perangkat (pesanan baru saja,
 * idempoten per Uuid), `sumber` Salesman + `idSalesman`/`idPerangkat` pengambil pesanan.
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
        public ?string $uuid = null,
        public SumberPesananGrosir $sumber = SumberPesananGrosir::BackOffice,
        public ?int $idSalesman = null,
        public ?int $idPerangkat = null,
    ) {}
}
