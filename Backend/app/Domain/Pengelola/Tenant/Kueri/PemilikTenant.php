<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Kueri;

use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Owner aktif satu tenant, penerima notifikasi tindakan pengelola (P-07). `TenantPengguna` & `Pengguna` bukan model
 * `MilikTenant` (BR-00.1), jadi dibaca langsung; kata sandi & PIN tidak pernah diambil.
 */
final class PemilikTenant
{
    /**
     * @return list<array{Nama: string, Email: string}>
     */
    public function Ambil(int $idTenant): array
    {
        return array_values(TenantPengguna::query()
            ->with('Pengguna:Id,Nama,Email')
            ->where('IdTenant', $idTenant)
            ->where('Pemilik', true)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->orderBy('Id')
            ->get()
            ->map(fn (TenantPengguna $anggota): array => ['Nama' => $anggota->Pengguna->Nama, 'Email' => $anggota->Pengguna->Email])
            ->all());
    }
}
