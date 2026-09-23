<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Pengelola\Tenant\Model\CatatanTenant;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Catatan internal tentang tenant (P-07, BR-P07.9). Tidak terlihat tenant; append-only.
 */
final class TulisCatatanTenant
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, string $isi): CatatanTenant
    {
        return DB::transaction(function () use ($pelaku, $tenant, $isi): CatatanTenant {
            $catatan = CatatanTenant::query()->create([
                'IdTenant' => $tenant->Id,
                'Isi' => $isi,
                'DibuatOleh' => $pelaku->Id,
            ]);

            $this->audit->Catat(
                'tenant.catatan.tulis',
                $catatan,
                nilaiBaru: ['Isi' => $isi],
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $catatan;
        });
    }
}
