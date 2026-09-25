<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Penangan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Laporan\Aksi\BangunUlangRingkasanPenjualanHarian;
use App\Domain\Penjualan\Peristiwa\PeristiwaDokumenPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * F-14a: setelah penjualan/void/retur diterima (peristiwa domain Penjualan, dikirim setelah commit), hitung ulang baris
 * `RingkasanPenjualanHarian` (tenant, outlet, tanggal bisnis) terkait di antrean. Efek non-kritis (aturan #10):
 * kegagalan tercatat sebagai job gagal dan diperbaiki perintah malam `laporan:bangun-ulang-ringkasan`. Idempoten
 * karena selalu menghitung ulang penuh dari dokumen sumber.
 */
final class PerbaruiRingkasanPenjualanHarian implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly BangunUlangRingkasanPenjualanHarian $bangunUlang,
    ) {}

    public function handle(PeristiwaDokumenPenjualan $peristiwa): void
    {
        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($peristiwa->AmbilIdTenant());

        try {
            $this->bangunUlang->Jalankan(CarbonImmutable::parse($peristiwa->AmbilTanggalBisnis()), $peristiwa->AmbilIdOutlet());
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }
}
