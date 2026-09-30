<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil per baris (disnapshot ke `PenjualanDetail`, BR-07.2). Alokasi dokumen ke baris memakai metode sisa terbesar
 * sehingga Σ baris selalu sama dengan angka dokumen. `TotalBaris` = Netto − DiskonPesanan + BiayaLayanan + BiayaKirim
 * + PajakEksklusif.
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
        /** Bagian ongkir netto yang dialokasikan ke baris ini (F-17 bagian 3); dasar pajak ongkirnya. */
        public Uang $biayaKirim,
    ) {}

    /**
     * `biayaKirim` sengaja tidak ikut di sini. Pemeriksa test vector membandingkan `Harapan.Baris` **persis** (bukan
     * hanya kunci yang ada, beda dengan tingkat dokumen), jadi menambah kunci baru akan mengubah nilai harapan 11
     * vektor lama — dilarang `.claude/rules/Pengujian.md`. Alokasi ongkir per baris tetap teruji lintas bahasa lewat
     * `TotalBaris`, dan nilainya dipakai langsung dari properti saat menyimpan `PenjualanDetail.BiayaKirim`.
     *
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
