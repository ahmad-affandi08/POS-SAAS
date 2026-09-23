<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Data;

/**
 * Tarif pajak master yang berlaku pada satu tanggal, untuk domain lain (misal usulan pajak panduan awal F-01) tanpa
 * memakai Model `TarifPajak` (CLAUDE.md #14). `tarif` = string desimal persen dari master (tidak pernah float).
 */
final readonly class DataTarifBerlaku
{
    public function __construct(
        public string $tarif,
        public string $berlakuMulai,
        public ?string $nomorDasarHukum,
        public bool $biayaLayananMasukDpp,
        public int $pengaliDppPembilang,
        public int $pengaliDppPenyebut,
    ) {}
}
