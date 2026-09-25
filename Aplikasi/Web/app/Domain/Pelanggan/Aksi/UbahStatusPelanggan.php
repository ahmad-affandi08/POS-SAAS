<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use Illuminate\Support\Facades\DB;

/**
 * Arsipkan / pulihkan pelanggan (F-16a). Pelanggan diarsipkan tidak muncul di pencarian POS, riwayatnya tetap.
 * Audit `pelanggan.arsipkan`/`pelanggan.pulihkan`.
 */
final class UbahStatusPelanggan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @throws PelanggaranAturanBisnis StatusTidakBerubah
     */
    public function Jalankan(Pelanggan $pelanggan, StatusPelanggan $status, int $idPengguna): Pelanggan
    {
        return DB::transaction(function () use ($pelanggan, $status, $idPengguna): Pelanggan {
            $terkunci = Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === $status) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', "Pelanggan {$terkunci->Nama} sudah {$status->AmbilLabel()}.", 'Umum');
            }

            $lama = $terkunci->Status;
            $terkunci->Status = $status;
            $terkunci->save();
            $this->audit->Catat(
                $status === StatusPelanggan::Diarsipkan ? 'pelanggan.arsipkan' : 'pelanggan.pulihkan',
                $terkunci,
                ['Status' => $lama->value],
                ['Status' => $status->value],
                idPengguna: $idPengguna,
            );

            return $terkunci;
        });
    }
}
