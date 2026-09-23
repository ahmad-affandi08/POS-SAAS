<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Model\KodeAktivasi;
use App\Domain\Organisasi\Model\Perangkat;
use Illuminate\Support\Facades\DB;

/**
 * F-02b / BR-02.3: cabut (revoke) perangkat, misal hilang atau dijual. Final: token perangkat langsung ditolak
 * (`PerangkatDicabut`) dan kode aktivasi yang belum dipakai dibatalkan. Kode perangkat tidak dipakai ulang dan
 * perangkat tidak lagi dihitung dalam batas paket.
 *
 * TODO F-07: batch sinkron yang dibuat offline sebelum `DicabutPada` tetap diterima dengan flag review; batch
 * setelahnya ditolak (BR-02.3, §18).
 */
final class CabutPerangkat
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Perangkat $perangkat): Perangkat
    {
        return DB::transaction(function () use ($perangkat): Perangkat {
            $perangkat = Perangkat::query()->lockForUpdate()->findOrFail($perangkat->Id);

            if ($perangkat->CekDicabut()) {
                return $perangkat;
            }

            $perangkat->DicabutPada = now();
            $perangkat->save();

            KodeAktivasi::query()
                ->where('IdTenant', $perangkat->IdTenant)
                ->where('IdPerangkat', $perangkat->Id)
                ->whereNull('DipakaiPada')
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $this->audit->Catat('perangkat.cabut', $perangkat, nilaiLama: ['Status' => 'Aktif'], nilaiBaru: [
                'Status' => 'Dicabut',
                'DicabutPada' => $perangkat->DicabutPada?->toIso8601String(),
            ]);

            return $perangkat;
        });
    }
}
