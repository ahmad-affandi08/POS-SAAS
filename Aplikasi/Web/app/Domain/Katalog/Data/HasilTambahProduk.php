<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Hasil tambah produk awal (F-01): nama yang ditambahkan dan yang dilewati karena namanya sudah ada.
 */
final readonly class HasilTambahProduk
{
    /**
     * @param  list<string>  $ditambahkan
     * @param  list<string>  $dilewati
     */
    public function __construct(
        public array $ditambahkan,
        public array $dilewati,
    ) {}
}
