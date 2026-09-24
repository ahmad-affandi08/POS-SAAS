<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

/**
 * Data harga yang dibutuhkan `PenentuHarga`: semua daftar harga tenant dan baris harga yang relevan. Sama dengan
 * bagian `DaftarHarga` + `ProdukHarga` katalog POS dan bagian `Katalog` test vector harga.
 */
final readonly class DataKatalogHarga
{
    /**
     * @param  list<DataDaftarHargaResolusi>  $daftarHarga
     * @param  list<DataBarisProdukHarga>  $harga
     */
    public function __construct(
        public array $daftarHarga,
        public array $harga,
    ) {}
}
