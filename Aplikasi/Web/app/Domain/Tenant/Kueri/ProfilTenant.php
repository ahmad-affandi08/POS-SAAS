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

    /** Tenant dengan Id ini ada (struk digital publik memasang konteks tenant dari kode struk). */
    public function CekAda(int $idTenant): bool
    {
        return Tenant::query()->whereKey($idTenant)->exists();
    }

    /** F-17: tenant pemilik slug URL publik (`/{slugTenant}/meja/{token}`); null bila tidak dikenal. */
    public function CariIdDariSlug(string $slug): ?int
    {
        $id = Tenant::query()->where('Slug', $slug)->value('Id');

        return $id === null ? null : (int) $id;
    }

    /** F-17: slug tenant untuk menyusun URL publik (QR meja). */
    public function AmbilSlug(int $idTenant): string
    {
        return (string) Tenant::query()->whereKey($idTenant)->value('Slug');
    }
}
