<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Model\Tenant;

/**
 * Profil usaha & pengaturan tenant untuk domain lain (F-01: panduan awal, usulan pajak, jenis produk bawaan).
 */
final class ProfilTenant
{
    /**
     * @return array{Nama: string, Npwp: string|null, Pkp: bool, ZonaWaktu: string, Pengaturan: array<string, mixed>, PathLogo: string|null}
     */
    public function Ambil(int $idTenant): array
    {
        $tenant = Tenant::query()->whereKey($idTenant)->firstOrFail();
        $pengaturan = $tenant->Pengaturan ?? [];

        return [
            'Nama' => $tenant->Nama,
            'Npwp' => $tenant->Npwp,
            'Pkp' => $tenant->Pkp,
            'ZonaWaktu' => $tenant->ZonaWaktu,
            'Pengaturan' => $pengaturan,
            'PathLogo' => is_string($pengaturan['PathLogo'] ?? null) ? $pengaturan['PathLogo'] : null,
        ];
    }
}
