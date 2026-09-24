<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menulis `BatasKasKeluar` & `ShiftBersama` ke `Tenant.Pengaturan` tenant aktif (F-06). Dipanggil
 * `Kasir\Aksi\UbahPengaturanKasir` yang memvalidasi dan mencatat audit. Kunci lain di `Pengaturan` tidak disentuh.
 */
final class UbahPengaturanKasirTenant
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
    ) {}

    public function Jalankan(DataPengaturanKasir $data): void
    {
        DB::transaction(function () use ($data): void {
            $tenant = $this->penguncian->Kunci($this->konteks->Wajib());
            $pengaturan = $tenant->Pengaturan ?? [];
            $pengaturan['BatasKasKeluar'] = $data->batasKasKeluar->KeString();
            $pengaturan['ShiftBersama'] = $data->shiftBersama;
            $tenant->Pengaturan = $pengaturan;

            if ($tenant->isDirty('Pengaturan')) {
                $tenant->save();
            }
        });
    }
}
