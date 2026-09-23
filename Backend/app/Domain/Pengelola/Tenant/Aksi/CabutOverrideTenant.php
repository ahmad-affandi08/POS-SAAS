<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Model\OverrideTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengakhiri override batas/fitur lebih awal (P-07, BR-P07.7): `BerakhirPada` diisi waktu sekarang, baris tetap ada
 * sebagai riwayat. Jejak perpanjangan trial tidak bisa dicabut.
 */
final class CabutOverrideTenant
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, OverrideTenant $override, string $alasan): OverrideTenant
    {
        return DB::transaction(function () use ($pelaku, $override, $alasan): OverrideTenant {
            $override = OverrideTenant::query()->lockForUpdate()->findOrFail($override->Id);

            if ($override->Jenis === JenisOverride::Trial) {
                throw new PelanggaranAturanBisnis('BR-P07.7', 'Perpanjangan trial tidak bisa dicabut.');
            }

            if (! $override->CekAktif()) {
                throw new PelanggaranAturanBisnis('BR-P07.7', 'Override ini sudah berakhir.');
            }

            $lama = $override->BerakhirPada->toIso8601String();
            $override->update(['BerakhirPada' => now()]);

            $this->audit->Catat(
                'tenant.override.cabut',
                $override,
                nilaiLama: ['Kunci' => $override->Kunci, 'BerakhirPada' => $lama],
                nilaiBaru: ['Kunci' => $override->Kunci, 'BerakhirPada' => $override->BerakhirPada->toIso8601String()],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $override->IdTenant,
            );

            return $override;
        });
    }
}
