<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Ringkasan penjualan satu shift untuk tutup shift & laporan X/Z (F-11), API baca publik domain Penjualan untuk
 * domain Kasir (CLAUDE.md #14).
 *
 * - Angka penjualan (`jumlahTransaksi` s.d. `perMetode`) hanya dari penjualan yang tidak di-void (`Lunas`,
 *   `Diretur`); retur ditampilkan terpisah (`jumlahRetur`, `nominalRetur`).
 * - `penjualanKotor` = Σ bruto baris (sebelum diskon), `totalDiskon` = diskon baris + pesanan, `penjualanBersih` =
 *   kotor − diskon (sebelum biaya layanan, pajak eksklusif, dan pembulatan), `totalAkhir` = Σ total dibayar pelanggan.
 * - `tunaiMasukBersih` = uang tunai yang masuk laci dari penjualan (tunai diterima − kembalian), termasuk penjualan
 *   yang kemudian di-void karena uangnya sempat masuk; pengembaliannya dihitung di `refundTunai`.
 * - `refundTunai` = uang tunai yang keluar dari laci shift ini untuk void & retur (F-09).
 */
final readonly class DataRingkasanPenjualanShift
{
    /**
     * @param  list<DataMetodeRingkasanShift>  $perMetode
     */
    public function __construct(
        public int $jumlahTransaksi,
        public Uang $penjualanKotor,
        public Uang $totalDiskon,
        public Uang $penjualanBersih,
        public Uang $totalPajak,
        public Uang $biayaLayanan,
        public Uang $pembulatan,
        public Uang $totalAkhir,
        public array $perMetode,
        public Uang $tunaiMasukBersih,
        public Uang $refundTunai,
        public int $jumlahVoid,
        public Uang $nominalVoid,
        public int $jumlahRetur,
        public Uang $nominalRetur,
    ) {}

    /** Total bersih satu metode (Rp 0 bila tidak ada transaksi dengan metode itu). */
    public function AmbilJumlahMetode(string $uuidMetode): Uang
    {
        foreach ($this->perMetode as $metode) {
            if ($metode->uuidMetode === $uuidMetode) {
                return $metode->jumlah;
            }
        }

        return Uang::Nol();
    }

    /**
     * Bentuk JSON (laporan shift back-office).
     *
     * @return array<string, mixed>
     */
    public function KeLarik(): array
    {
        return [
            'JumlahTransaksi' => $this->jumlahTransaksi,
            'PenjualanKotor' => $this->penjualanKotor->KeString(),
            'TotalDiskon' => $this->totalDiskon->KeString(),
            'PenjualanBersih' => $this->penjualanBersih->KeString(),
            'TotalPajak' => $this->totalPajak->KeString(),
            'BiayaLayanan' => $this->biayaLayanan->KeString(),
            'Pembulatan' => $this->pembulatan->KeString(),
            'TotalAkhir' => $this->totalAkhir->KeString(),
            'PerMetode' => array_map(fn (DataMetodeRingkasanShift $m): array => $m->KeLarik(), $this->perMetode),
            'TunaiMasukBersih' => $this->tunaiMasukBersih->KeString(),
            'RefundTunai' => $this->refundTunai->KeString(),
            'JumlahVoid' => $this->jumlahVoid,
            'NominalVoid' => $this->nominalVoid->KeString(),
            'JumlahRetur' => $this->jumlahRetur,
            'NominalRetur' => $this->nominalRetur->KeString(),
        ];
    }
}
