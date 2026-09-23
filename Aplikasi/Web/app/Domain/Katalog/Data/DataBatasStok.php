<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Stok minimum/maksimum produk di satu lokasi stok (F-03 `SimpanBatasStokProduk`), dalam satuan dasar.
 * Null = tidak diatur; baris yang keduanya null dihapus.
 */
final readonly class DataBatasStok
{
    public function __construct(
        public int $idGudang,
        public ?Kuantitas $minimum,
        public ?Kuantitas $maksimum,
    ) {}
}
