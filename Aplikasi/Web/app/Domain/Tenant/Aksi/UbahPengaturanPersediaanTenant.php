<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menulis `MetodeHpp` & `StokBolehMinus` ke `Tenant.Pengaturan` tenant aktif (DesainF05a C.8). Dipanggil
 * `Persediaan\Aksi\UbahPengaturanPersediaan`, yang sudah memegang kunci X Tenant, memeriksa `MetodeHppTerkunci`, dan
 * mencatat audit; kunci ulang di sini re-entrant (baris yang sama, transaksi yang sama). Kunci lain di `Pengaturan`
 * tidak disentuh. `batasPersetujuanPenyesuaian` null = tidak diubah (F-05b).
 */
final class UbahPengaturanPersediaanTenant
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
    ) {}

    public function Jalankan(MetodeHpp $metode, bool $bolehMinus, ?Uang $batasPersetujuanPenyesuaian = null): void
    {
        DB::transaction(function () use ($metode, $bolehMinus, $batasPersetujuanPenyesuaian): void {
            $tenant = $this->penguncian->Kunci($this->konteks->Wajib());
            $pengaturan = $tenant->Pengaturan ?? [];
            $pengaturan['MetodeHpp'] = $metode->value;
            $pengaturan['StokBolehMinus'] = $bolehMinus;

            if ($batasPersetujuanPenyesuaian !== null) {
                $pengaturan['BatasPersetujuanPenyesuaian'] = $batasPersetujuanPenyesuaian->KeString();
            }

            $tenant->Pengaturan = $pengaturan;

            if ($tenant->isDirty('Pengaturan')) {
                $tenant->save();
            }
        });
    }
}
