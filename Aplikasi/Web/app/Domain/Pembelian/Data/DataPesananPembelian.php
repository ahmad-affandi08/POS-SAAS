<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `SimpanPesananPembelian` (F-04 fase 1). `idGudang` sudah diperiksa batas outlet pelaku oleh kontroler;
 * `terminHari` null = termin bawaan pemasok.
 */
final readonly class DataPesananPembelian
{
    /**
     * @param  list<DataBarisPesananPembelian>  $baris
     */
    public function __construct(
        public string $uuidPemasok,
        public int $idGudang,
        public CarbonImmutable $tanggal,
        public ?CarbonImmutable $perkiraanTiba,
        public ?int $terminHari,
        public Uang $ongkir,
        public ?string $catatan,
        public array $baris,
        public int $idPengguna,
        public bool $dibuatOtomatis = false,
    ) {}
}
