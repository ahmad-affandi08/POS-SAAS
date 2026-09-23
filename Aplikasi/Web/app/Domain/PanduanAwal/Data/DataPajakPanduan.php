<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

/**
 * Isian langkah 3 panduan awal: pajak outlet. `persenBiayaLayanan` = string desimal (tidak pernah float).
 */
final readonly class DataPajakPanduan
{
    public function __construct(
        public bool $pungutPbjt,
        public bool $biayaLayananAktif,
        public string $persenBiayaLayanan,
        public bool $hargaTermasukPajak,
    ) {}
}
