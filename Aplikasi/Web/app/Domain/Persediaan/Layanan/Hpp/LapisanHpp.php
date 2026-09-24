<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Satu lapisan biaya FIFO di memori (cermin baris `LapisanFifo`, DesainF05a C.3). `id` null = lapisan baru dari
 * dokumen yang sedang dicatat (`kunciBarisSumber` menautkannya ke baris mutasinya); `berubah` = perlu disimpan.
 */
final class LapisanHpp
{
    public bool $berubah = false;

    public function __construct(
        public ?int $id,
        public ?int $idMutasiSumber,
        public ?string $kunciBarisSumber,
        public ?int $idBatchStok,
        public Kuantitas $jumlahAwal,
        public Kuantitas $jumlahSisa,
        public BigDecimal $hppSatuan,
        public Uang $nilaiAwal,
        public Uang $nilaiSisa,
    ) {}

    public function CekHabis(): bool
    {
        return AritmetikaHpp::CekNol($this->jumlahSisa);
    }

    /**
     * Mengambil `ambil` (> 0, ≤ sisa) dari lapisan: bila mengambil seluruh sisa, nilainya = NilaiSisa (sisa sen ikut
     * habis); selain itu min(Nilai(ambil, HppSatuan), NilaiSisa). Mengembalikan nilai yang diambil (≥ 0).
     */
    public function Konsumsi(Kuantitas $ambil): Uang
    {
        if (! AritmetikaHpp::CekPositif($ambil) || $ambil->Bandingkan($this->jumlahSisa) > 0) {
            throw new InvalidArgumentException('Jumlah yang diambil dari lapisan FIFO tidak valid.');
        }

        $nilai = $ambil->SamaDengan($this->jumlahSisa)
            ? $this->nilaiSisa
            : AritmetikaHpp::AmbilMinimum(AritmetikaHpp::Nilai($ambil, $this->hppSatuan), $this->nilaiSisa);

        $this->jumlahSisa = $this->jumlahSisa->Kurangi($ambil);
        $this->nilaiSisa = $this->nilaiSisa->Kurangi($nilai);
        $this->berubah = true;

        return $nilai;
    }
}
