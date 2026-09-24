<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;

/**
 * Permintaan generasi varian (F-03 `GenerasikanVarian`): atribut yang digabung ke definisi induk, jenis anak, dan
 * harga dasar anak baru (butuh `bolehUbahHarga`). Null = anak dibuat tanpa harga.
 */
final readonly class DataGenerasiVarian
{
    /**
     * @param  list<DataAtributVarian>  $atribut
     */
    public function __construct(
        public array $atribut,
        public JenisProduk $jenisAnak,
        public ?Uang $hargaDasar,
        public bool $bolehUbahHarga,
    ) {}
}
