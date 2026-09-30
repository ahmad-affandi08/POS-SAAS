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
        // F-17 bagian 3: ongkir ikut DPP pajak ini. Paling akhir dan berbawaan supaya perangkat versi lama yang belum
        // mengirimnya tetap diterima (CLAUDE.md #16).
        public bool $kenaBiayaKirim = false,
    ) {}

    /**
     * @return array{Kode: string, Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, DasarPengenaan: string, KenaBiayaKirim: bool}
     */
    public function KeLarik(): array
    {
        return [
            'Kode' => $this->kode,
            'Tarif' => (string) $this->tarif->toScale(6),
            'PengaliDppPembilang' => $this->pengaliDppPembilang,
            'PengaliDppPenyebut' => $this->pengaliDppPenyebut,
            'DasarPengenaan' => $this->dasarPengenaan->value,
            'KenaBiayaKirim' => $this->kenaBiayaKirim,
        ];
    }
}
