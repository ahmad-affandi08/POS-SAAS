<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Satu baris item outbox `PesananTerbuka.Tambah` (Uuid baris dari perangkat). Uang & jumlah string desimal.
 */
final readonly class DataBarisPesananTerbuka
{
    /**
     * @param  list<array{UuidPilihan: string, Nama: string, Harga: string}>  $pilihan
     */
    public function __construct(
        public string $uuid,
        public string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public string $jumlah,
        public string $hargaSatuan,
        public string $hargaPilihan,
        public array $pilihan,
        public ?string $catatan,
    ) {}
}
