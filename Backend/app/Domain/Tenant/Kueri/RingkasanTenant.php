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
     * @return list<array{Id: int, Uuid: string, Nama: string, Slug: string}>
     */
    public function Ambil(array $idTenant): array
    {
        if ($idTenant === []) {
            return [];
        }

        return array_values(Tenant::query()->whereKey($idTenant)->orderBy('Nama')->get()->map(fn (Tenant $tenant): array => [
            'Id' => $tenant->Id,
            'Uuid' => $tenant->Uuid,
            'Nama' => $tenant->Nama,
            'Slug' => $tenant->Slug,
        ])->all());
    }
}
