<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Enum\KursusPesanan;

/**
 * Satu baris item outbox `PesananTerbuka.Tambah` (Uuid baris dari perangkat). Uang & jumlah string desimal; `kursus` K-13 (opsional).
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
        public ?KursusPesanan $kursus = null,
    ) {}
}
