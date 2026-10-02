<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Laporan\Model\LanggananInsightMingguan;
use Illuminate\Support\Facades\DB;

/** Pengguna memilih menerima atau berhenti menerima insight mingguan X6 lewat email (v3.79). */
final class UbahLanggananInsightMingguan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(int $idPengguna, bool $aktif): void
    {
        DB::transaction(function () use ($idPengguna, $aktif): void {
            $baris = LanggananInsightMingguan::query()->where('IdPengguna', $idPengguna)->lockForUpdate()->first()
                ?? new LanggananInsightMingguan(['IdPengguna' => $idPengguna]);
            $lama = $baris->exists ? $baris->Aktif : null;
            $baris->Aktif = $aktif;
            $baris->save();

            if ($lama !== $aktif) {
                $this->audit->Catat('laporan.insight-mingguan.ubah', $baris, $lama === null ? null : ['Aktif' => $lama], ['Aktif' => $aktif]);
            }
        });
    }
}
