<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Illuminate\Support\Facades\DB;

/**
 * Arsipkan/pulihkan tier (F-16b). Tier diarsipkan tidak dipakai evaluasi otomatis berikutnya dan tidak bisa dipilih;
 * pelanggan yang masih memegangnya tetap sampai dievaluasi ulang/diubah. Audit `tier-pelanggan.arsipkan|pulihkan`.
 */
final class UbahStatusTierPelanggan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(TierPelanggan $tier, StatusPelanggan $status, int $idPengguna): TierPelanggan
    {
        return DB::transaction(function () use ($tier, $status, $idPengguna): TierPelanggan {
            $terkunci = TierPelanggan::query()->whereKey($tier->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === $status) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', "Tier {$terkunci->Nama} sudah {$status->AmbilLabel()}.", 'Umum');
            }

            if ($status === StatusPelanggan::Aktif && TierPelanggan::query()->where('Status', 'Aktif')->count() >= SimpanTierPelanggan::BATAS_AKTIF) {
                throw new PelanggaranAturanBisnis('BatasTier', 'Paling banyak '.SimpanTierPelanggan::BATAS_AKTIF.' tier aktif.', 'Umum');
            }

            $lama = $terkunci->Status;
            $terkunci->Status = $status;
            $terkunci->save();
            $this->audit->Catat(
                $status === StatusPelanggan::Diarsipkan ? 'tier-pelanggan.arsipkan' : 'tier-pelanggan.pulihkan',
                $terkunci,
                ['Status' => $lama->value],
                ['Status' => $status->value],
                idPengguna: $idPengguna,
            );

            return $terkunci;
        });
    }
}
