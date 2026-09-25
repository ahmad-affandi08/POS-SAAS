<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Data;

/**
 * Satu baris dokumen yang dikirim ke dapur (F-10b). `pilihan` = nama pilihan/modifier saja.
 */
final readonly class DataBarisKirimDapur
{
    /**
     * @param  list<string>  $pilihan
     */
    public function __construct(
        public string $uuidBaris,
        public int $idProduk,
        public string $namaProduk,
        public string $jumlah,
        public array $pilihan,
        public ?string $catatan,
    ) {}
}
