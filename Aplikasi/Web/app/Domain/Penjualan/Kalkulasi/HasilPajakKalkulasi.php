<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Rincian satu kode pajak dokumen: DPP (dibulatkan dari pecahan eksak) dan jumlah pajak (eksklusif + inklusif,
 * masing-masing dibulatkan per dokumen).
 */
final readonly class HasilPajakKalkulasi
{
    public function __construct(
        public string $kode,
        public Uang $dpp,
        public Uang $jumlah,
    ) {}

    /**
     * @return array{Dpp: string, Jumlah: string}
     */
    public function KeLarik(): array
    {
        return ['Dpp' => $this->dpp->KeString(), 'Jumlah' => $this->jumlah->KeString()];
    }
}
