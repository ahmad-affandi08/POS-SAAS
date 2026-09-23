<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Data;

final readonly class DataFitur
{
    public function __construct(
        public string $kunci,
        public string $nama,
        public string $modul,
        public ?string $keterangan,
    ) {}
}
