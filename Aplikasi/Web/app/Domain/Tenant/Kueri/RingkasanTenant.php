<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Model\Tenant;

/**
 * Nama & slug tenant untuk pemilih tenant dan kepala back-office (BR-00.1).
 */
final class RingkasanTenant
{
    /**
     * @param  list<int>  $idTenant
     * @return list<array{Id: int, Uuid: string, Nama: string, Slug: string, PathLogo: string|null}>
     */
    public function Ambil(array $idTenant): array
    {
        if ($idTenant === []) {
            return [];
        }

        return array_values(Tenant::query()->whereKey($idTenant)->orderBy('Nama')->get()->map(function (Tenant $tenant): array {
            $pengaturan = $tenant->Pengaturan ?? [];
            $pathLogo = is_string($pengaturan['PathLogo'] ?? null) ? $pengaturan['PathLogo'] : null;

            return [
                'Id' => $tenant->Id,
                'Uuid' => $tenant->Uuid,
                'Nama' => $tenant->Nama,
                'Slug' => $tenant->Slug,
                'PathLogo' => $pathLogo,
            ];
        })->all());
    }
}
