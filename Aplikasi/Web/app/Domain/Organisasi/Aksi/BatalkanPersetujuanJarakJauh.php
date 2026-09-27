<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Organisasi\Enum\StatusPermintaanPersetujuan;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use Illuminate\Support\Facades\DB;

/**
 * Kasir berhenti menunggu persetujuan jarak jauh (X4): permintaan yang masih Menunggu menjadi Dibatalkan (hilang dari
 * antrean Aplikasi Owner). Yang sudah final dibiarkan (idempoten). Juga menandai Kedaluwarsa bila sudah lewat waktu.
 */
final class BatalkanPersetujuanJarakJauh
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PutuskanPersetujuanJarakJauh $putuskan,
    ) {}

    public function Jalankan(PermintaanPersetujuan $permintaan): PermintaanPersetujuan
    {
        return DB::transaction(function () use ($permintaan): PermintaanPersetujuan {
            $p = PermintaanPersetujuan::query()->whereKey($permintaan->Id)->lockForUpdate()->firstOrFail();

            if ($p->CekLewatWaktu()) {
                $this->putuskan->Kedaluwarsakan($p);
            } elseif ($p->Status === StatusPermintaanPersetujuan::Menunggu) {
                $p->UbahStatus(StatusPermintaanPersetujuan::Dibatalkan);
                $p->save();
                $this->riwayat->Catat('PermintaanPersetujuan', $p->Id, StatusPermintaanPersetujuan::Menunggu->value, StatusPermintaanPersetujuan::Dibatalkan->value, $p->IdPemohon);
            }

            return $p;
        });
    }
}
