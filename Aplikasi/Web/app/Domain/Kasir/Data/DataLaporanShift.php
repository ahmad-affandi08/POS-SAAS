<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Data\DataRingkasanPenjualanShift;

/**
 * Laporan shift X/Z (F-11) dari data server: ringkasan penjualan, kas non-penjualan, dan kas seharusnya.
 * Kas seharusnya = kas awal + penjualan tunai bersih + kas masuk − kas keluar − setoran − refund tunai (void & retur).
 */
final readonly class DataLaporanShift
{
    public function __construct(
        public Uang $kasAwal,
        public Uang $totalMasuk,
        public Uang $totalKeluar,
        public Uang $totalSetoran,
        public DataRingkasanPenjualanShift $penjualan,
        public Uang $kasSeharusnya,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function KeLarik(): array
    {
        return [
            'Penjualan' => $this->penjualan->KeLarik(),
            'Kas' => [
                'KasAwal' => $this->kasAwal->KeString(),
                'TunaiMasukBersih' => $this->penjualan->tunaiMasukBersih->KeString(),
                'TotalMasuk' => $this->totalMasuk->KeString(),
                'TotalKeluar' => $this->totalKeluar->KeString(),
                'TotalSetoran' => $this->totalSetoran->KeString(),
                'RefundTunai' => $this->penjualan->refundTunai->KeString(),
                'KasSeharusnya' => $this->kasSeharusnya->KeString(),
            ],
        ];
    }
}
