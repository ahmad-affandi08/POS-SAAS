<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Kiriman pesanan tamu dari halaman QR meja (F-17). `uuid` = ULID buatan peramban (idempotensi).
 */
final readonly class DataPesanSendiri
{
    /**
     * @param  list<DataBarisPesanSendiri>  $baris
     */
    public function __construct(
        public string $uuid,
        public ?string $namaPemesan,
        public ?string $catatan,
        public array $baris,
    ) {}
}
