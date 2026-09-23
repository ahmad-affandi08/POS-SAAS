<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.1): melengkapi `Tenant.Pengaturan` dengan nilai bawaan template. Aditif: hanya kunci yang belum ada;
 * nilai yang sudah diubah tenant tidak pernah ditimpa.
 */
final class LengkapiPengaturanTenant
{
    public function __construct(
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $bawaan
     * @return list<string> kunci yang ditambahkan
     */
    public function Jalankan(int $idTenant, array $bawaan): array
    {
        return DB::transaction(function () use ($idTenant, $bawaan): array {
            $tenant = $this->penguncian->Kunci($idTenant);
            $pengaturan = $tenant->Pengaturan ?? [];
            $ditambahkan = [];

            foreach ($bawaan as $kunci => $nilai) {
                if (! array_key_exists($kunci, $pengaturan)) {
                    $pengaturan[$kunci] = $nilai;
                    $ditambahkan[] = $kunci;
                }
            }

            if ($ditambahkan === []) {
                return [];
            }

            $tenant->Pengaturan = $pengaturan;
            $tenant->save();
            $this->audit->Catat('tenant.pengaturan.lengkapi', $tenant, nilaiBaru: array_intersect_key($pengaturan, array_flip($ditambahkan)), idTenant: $idTenant);

            return $ditambahkan;
        });
    }
}
