<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Data;

use App\Domain\Referensi\Enum\JenisHariLibur;
use Carbon\CarbonImmutable;

final readonly class DataHariLibur
{
    public function __construct(
        public CarbonImmutable $tanggal,
        public string $nama,
        public JenisHariLibur $jenis,
        public ?string $nomorDasarHukum,
    ) {}
}
