<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Data;

/**
 * Isian kelompok pilihan beserta seluruh pilihannya (F-03 C.4). `bolehUbahHarga` = pelaku punya izin
 * `produk.harga.ubah` (harga pilihan baru/berubah selain 0 memerlukannya).
 */
final readonly class DataKelompokPilihan
{
    /**
     * @param  list<DataPilihan>  $pilihan
     */
    public function __construct(
        public string $nama,
        public int $minimalPilih,
        public int $maksimalPilih,
        public int $urutan,
        public array $pilihan,
        public bool $bolehUbahHarga,
    ) {}
}
