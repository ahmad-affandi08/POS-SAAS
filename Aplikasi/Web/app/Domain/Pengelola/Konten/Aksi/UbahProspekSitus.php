<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Enum\StatusProspek;
use App\Domain\Situs\Model\ProspekSitus;
use Illuminate\Support\Facades\DB;

/**
 * Situs pemasaran bagian B: tindak lanjut prospek (status & catatan). Perubahan pertama dari Baru mencatat penangan &
 * waktu ditangani. Tercatat di log audit pengelola (tanpa nomor/email).
 */
final class UbahProspekSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, ProspekSitus $prospek, StatusProspek $status, ?string $catatan): ProspekSitus
    {
        return DB::transaction(function () use ($pelaku, $prospek, $status, $catatan): ProspekSitus {
            $lama = ['Status' => $prospek->Status->value, 'Catatan' => $prospek->Catatan];
            $prospek->Status = $status;
            $prospek->Catatan = $catatan;

            if ($status !== StatusProspek::Baru && $prospek->DitanganiPada === null) {
                $prospek->DitanganiPada = now();
                $prospek->IdPenggunaPengelolaPenangan = $pelaku->Id;
            }

            $prospek->save();
            $this->audit->Catat('situs.prospek.ubah', $prospek, $lama, ['Status' => $status->value, 'Catatan' => $catatan]);

            return $prospek;
        });
    }
}
