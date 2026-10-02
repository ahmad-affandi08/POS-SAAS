<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Kueri\PengaturanBarcodeTimbanganTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan pengaturan barcode timbangan (§9.3, v3.55) ke `Tenant.Pengaturan.BarcodeTimbangan` tenant aktif. Kunci lain
 * di `Pengaturan` tidak disentuh. Tanpa perubahan = tidak ada yang ditulis. Audit `kasir.barcode-timbangan.ubah`.
 */
final class UbahBarcodeTimbanganTenant
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PengaturanBarcodeTimbanganTenant $pengaturan,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $awalan
     */
    public function Jalankan(bool $aktif, array $awalan, string $nilai): void
    {
        DB::transaction(function () use ($aktif, $awalan, $nilai): void {
            $tenant = $this->penguncian->Kunci($this->konteks->Wajib());
            $lama = $this->pengaturan->Ambil();
            $baru = PengaturanBarcodeTimbanganTenant::Rapikan(['Aktif' => $aktif, 'Awalan' => $awalan, 'Nilai' => $nilai]);

            if ($lama === $baru) {
                return;
            }

            $pengaturan = $tenant->Pengaturan ?? [];
            $pengaturan['BarcodeTimbangan'] = $baru;
            $tenant->Pengaturan = $pengaturan;
            $tenant->save();
            $this->audit->Catat('kasir.barcode-timbangan.ubah', nilaiLama: $lama, nilaiBaru: $baru);
        });
    }
}
