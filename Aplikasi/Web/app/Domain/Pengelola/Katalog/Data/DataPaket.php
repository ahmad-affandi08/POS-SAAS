<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Data;

final readonly class DataPaket
{
    /**
     * @param  array<string, int|null>  $batas  kunci = Paket::KOLOM_BATAS, null = tak terbatas
     * @param  list<string>  $kunciFitur
     */
    public function __construct(
        public string $kode,
        public string $nama,
        public ?string $keterangan,
        public bool $hargaNegosiasi,
        public int $masaTrialHari,
        public array $batas,
        public array $kunciFitur,
        public int $urutan,
    ) {}
}
