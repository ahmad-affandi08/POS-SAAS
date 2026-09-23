<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Data;

use Carbon\CarbonImmutable;

final readonly class DataTarifPajak
{
    public function __construct(
        public string $kodeJenisPajak,
        public string $tarif,
        public int $pengaliDppPembilang,
        public int $pengaliDppPenyebut,
        public ?string $kodeWilayah,
        public bool $biayaLayananMasukDpp,
        public CarbonImmutable $berlakuMulai,
        public ?string $nomorDasarHukum,
        public ?string $tautanDasarHukum,
    ) {}
}
