<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Illuminate\Support\Facades\DB;

/**
 * Atur tier satu pelanggan secara manual (F-16b, izin `pelanggan.kelola`). `tierTetap` = dikunci, tidak diubah
 * evaluasi otomatis (misal reseller). Tier harus aktif. Audit `pelanggan.tier`.
 */
final class AturTierPelanggan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Pelanggan $pelanggan, ?TierPelanggan $tier, bool $tierTetap, int $idPengguna): Pelanggan
    {
        if ($tier !== null && $tier->Status !== StatusPelanggan::Aktif) {
            throw new PelanggaranAturanBisnis('TierDiarsipkan', "Tier {$tier->Nama} diarsipkan.", 'UuidTier');
        }

        return DB::transaction(function () use ($pelanggan, $tier, $tierTetap, $idPengguna): Pelanggan {
            $terkunci = Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();
            $lama = ['IdTier' => $terkunci->IdTier, 'TierTetap' => $terkunci->TierTetap];
            $terkunci->IdTier = $tier?->Id;
            $terkunci->TierTetap = $tierTetap;
            $terkunci->save();
            $baru = ['IdTier' => $terkunci->IdTier, 'TierTetap' => $terkunci->TierTetap];

            if ($lama !== $baru) {
                $this->audit->Catat('pelanggan.tier', $terkunci, $lama, $baru, idPengguna: $idPengguna);
            }

            return $terkunci;
        });
    }
}
