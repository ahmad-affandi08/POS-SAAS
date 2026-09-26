<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Model\Perangkat;
use Illuminate\Support\Facades\DB;

/**
 * PRD §17.2.5 & §17.2.5a (v1.96): aplikasi kasir melaporkan profil hardware perangkat (merek/model, printer & cara
 * sambungnya, hasil Wizard Uji Perangkat) ke `Perangkat.ProfilHardware` untuk dukungan teknis dan daftar kompatibilitas
 * perangkat. Tanpa data pribadi. Hanya disimpan & diaudit (`perangkat.profil-hardware`) bila isinya berubah.
 */
final class SimpanProfilHardware
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  array<string, mixed>  $profil  sudah divalidasi kontroler
     */
    public function Jalankan(Perangkat $perangkat, array $profil): void
    {
        DB::transaction(function () use ($perangkat, $profil): void {
            $terkunci = Perangkat::query()->lockForUpdate()->findOrFail($perangkat->Id);
            $lama = $terkunci->ProfilHardware;
            $tanpaWaktu = fn (?array $p): ?array => $p === null ? null : array_diff_key($p, ['DilaporkanPada' => true]);

            if ($tanpaWaktu($lama) == $profil) {
                return;
            }

            $terkunci->ProfilHardware = [...$profil, 'DilaporkanPada' => now()->utc()->toIso8601ZuluString()];
            $terkunci->save();
            $this->audit->Catat('perangkat.profil-hardware', $terkunci, nilaiLama: $lama, nilaiBaru: $profil, idTenant: $terkunci->IdTenant);
        });
    }
}
