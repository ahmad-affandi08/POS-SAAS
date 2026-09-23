<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\SumberPerubahanKatalog;

/**
 * Satu anak varian (F-03 `TambahVarianAnak`, dipakai impor): kombinasi nilai atribut, misal
 * `[['Nama' => 'Ukuran', 'Nilai' => 'M']]`. `sku` null = `{SkuInduk}-{NN}`. `hargaDasar` butuh `bolehUbahHarga`.
 */
final readonly class DataVarianAnak
{
    /**
     * @param  list<array{Nama: string, Nilai: string}>  $atribut
     */
    public function __construct(
        public array $atribut,
        public ?string $sku,
        public JenisProduk $jenis,
        public ?Uang $hargaDasar,
        public bool $bolehUbahHarga,
        public SumberPerubahanKatalog $sumber,
    ) {}
}
