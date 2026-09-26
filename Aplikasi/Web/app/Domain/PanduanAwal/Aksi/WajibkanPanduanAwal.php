<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;
use Illuminate\Support\Facades\DB;

/**
 * D-24: tenant yang baru mendaftar wajib menyelesaikan panduan awal (semua langkah kecuali Perangkat) sebelum membuka
 * back-office. Dipanggil sekali saat pendaftaran (F-00); tenant lama tidak pernah melewati Aksi ini sehingga bebas.
 */
final class WajibkanPanduanAwal
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Jalankan(int $idTenant): void
    {
        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);

        try {
            DB::transaction(function (): void {
                $progres = ProgresPanduanAwal::query()->firstOrCreate([], ['Wajib' => true]);

                if (! $progres->Wajib && $progres->SelesaiPada === null) {
                    $progres->forceFill(['Wajib' => true])->save();
                }
            });
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }
}
