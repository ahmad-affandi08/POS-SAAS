<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\PenandaTenant;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Penanda tenant Uji/Demo/Internal oleh Super Admin (P-07, BR-P07.8). Tenant berpenanda dikecualikan dari metrik
 * bisnis & tagihan. Null = tenant biasa. Alasan wajib karena memengaruhi pendapatan yang dilaporkan.
 */
final class UbahPenandaTenant
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, ?PenandaTenant $penanda, string $alasan): Tenant
    {
        return DB::transaction(function () use ($pelaku, $tenant, $penanda, $alasan): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->Id);
            $lama = $tenant->Penanda;

            if ($lama === $penanda) {
                throw new PelanggaranAturanBisnis('BR-P07.8', 'Penanda tidak berubah.', 'Penanda');
            }

            $tenant->update(['Penanda' => $penanda]);

            $this->audit->Catat(
                'tenant.penanda.ubah',
                $tenant,
                nilaiLama: ['Penanda' => $lama?->value],
                nilaiBaru: ['Penanda' => $penanda?->value],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $tenant;
        });
    }
}
