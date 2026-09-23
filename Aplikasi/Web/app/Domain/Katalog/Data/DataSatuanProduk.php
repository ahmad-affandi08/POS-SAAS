<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;

/**
 * Satu satuan produk di form/impor (F-03). `idProdukSatuan` null = satuan baru. `barcode` = set lengkap barcode
 * satuan ini (mengganti yang lama). `hargaAwal` hanya dipakai untuk satuan baru ([] = tanpa harga).
 */
final readonly class DataSatuanProduk
{
    /**
     * @param  list<string>  $barcode
     * @param  list<DataBarisHarga>  $hargaAwal
     */
    public function __construct(
        public ?int $idProdukSatuan,
        public int $idSatuan,
        public Kuantitas $konversiKeDasar,
        public bool $defaultJual,
        public bool $defaultBeli,
        public array $barcode,
        public array $hargaAwal,
    ) {}
}
