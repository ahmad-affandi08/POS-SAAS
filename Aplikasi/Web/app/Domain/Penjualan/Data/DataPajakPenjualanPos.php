<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use Brick\Math\BigDecimal;

/**
 * Snapshot satu jenis pajak dokumen dari perangkat (F-07b). Server mencocokkannya dengan `TarifPajak` terbit yang
 * berlaku (CLAUDE.md #12).
 */
final readonly class DataPajakPenjualanPos
{
    public function __construct(
        public string $kode,
        public BigDecimal $tarif,
        public int $pengaliDppPembilang,
        public int $pengaliDppPenyebut,
        public DasarPengenaanPajak $dasarPengenaan,
    ) {}

    /**
     * @return array{Kode: string, Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, DasarPengenaan: string}
     */
    public function KeLarik(): array
    {
        return [
            'Kode' => $this->kode,
            'Tarif' => (string) $this->tarif->toScale(6),
            'PengaliDppPembilang' => $this->pengaliDppPembilang,
            'PengaliDppPenyebut' => $this->pengaliDppPenyebut,
            'DasarPengenaan' => $this->dasarPengenaan->value,
        ];
    }
}
