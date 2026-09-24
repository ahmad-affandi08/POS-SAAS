<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil per baris (disnapshot ke `PenjualanDetail`, BR-07.2). Alokasi dokumen ke baris memakai metode sisa terbesar
 * sehingga Σ baris selalu sama dengan angka dokumen. `TotalBaris` = Netto − DiskonPesanan + BiayaLayanan +
 * PajakEksklusif.
 */
final readonly class HasilBarisKalkulasi
{
    public function __construct(
        public Uang $bruto,
        public Uang $diskon,
        public Uang $diskonPesanan,
        public Uang $biayaLayanan,
        public Uang $pajak,
        public Uang $pajakEksklusif,
        public Uang $totalBaris,
    ) {}

    /**
     * @return array<string, string>
     */
    public function KeLarik(): array
    {
        return [
            'Bruto' => $this->bruto->KeString(),
            'Diskon' => $this->diskon->KeString(),
            'DiskonPesanan' => $this->diskonPesanan->KeString(),
            'BiayaLayanan' => $this->biayaLayanan->KeString(),
            'Pajak' => $this->pajak->KeString(),
            'PajakEksklusif' => $this->pajakEksklusif->KeString(),
            'TotalBaris' => $this->totalBaris->KeString(),
        ];
    }
}
