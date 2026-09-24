<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Persediaan\Kueri\CekAdaMutasi;
use App\Domain\Tenant\Aksi\UbahPengaturanPersediaanTenant;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah metode HPP (BR-04.2, rata-rata bergerak/FIFO) dan izin stok minus tenant (BR-05.2) (DesainF05a C.8, H-4).
 *
 * Kunci X baris Tenant (L1) lebih dulu: semua mutasi stok memegang kunci S baris yang sama selama transaksinya,
 * sehingga tidak ada mutasi yang sedang berjalan saat metode dibaca dan diganti. Metode HPP terkunci begitu tenant
 * punya `MutasiStok` (`MetodeHppTerkunci`); izin stok minus boleh diubah kapan saja. Tanpa perubahan = tidak ada
 * yang ditulis. Audit `persediaan.pengaturan.ubah` berisi nilai lama & baru.
 */
final class UbahPengaturanPersediaan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly CekAdaMutasi $cekAdaMutasi,
        private readonly UbahPengaturanPersediaanTenant $ubahTenant,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(MetodeHpp $metode, bool $bolehMinus): void
    {
        DB::transaction(function () use ($metode, $bolehMinus): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $lama = $this->pengaturan->AmbilDenganKunciBaca();

            if ($lama->metodeHpp === $metode && $lama->stokBolehMinus === $bolehMinus) {
                return;
            }

            if ($lama->metodeHpp !== $metode && $this->cekAdaMutasi->Jalankan()) {
                throw new PelanggaranAturanBisnis(
                    'MetodeHppTerkunci',
                    "Metode HPP tidak bisa diubah karena sudah ada riwayat stok. Metode yang berlaku: {$lama->metodeHpp->AmbilLabel()}.",
                    'MetodeHpp',
                );
            }

            $this->ubahTenant->Jalankan($metode, $bolehMinus);
            $this->audit->Catat(
                'persediaan.pengaturan.ubah',
                nilaiLama: ['MetodeHpp' => $lama->metodeHpp->value, 'StokBolehMinus' => $lama->stokBolehMinus],
                nilaiBaru: ['MetodeHpp' => $metode->value, 'StokBolehMinus' => $bolehMinus],
            );
        }, max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
    }
}
