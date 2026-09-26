<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Baris pesanan tamu dari halaman QR meja (F-17). Harga tidak pernah diterima dari peramban. `uuidVarian` = anak varian
 * pilihan tamu bila `uuidProduk` induk varian (PRD v2.06).
 */
final readonly class DataBarisPesanSendiri
{
    /**
     * @param  list<string>  $pilihan  Uuid pilihan
     */
    public function __construct(
        public string $uuid,
        public string $uuidProduk,
        public int $jumlah,
        public array $pilihan,
        public ?string $catatan,
        public ?string $uuidVarian = null,
    ) {}
}
