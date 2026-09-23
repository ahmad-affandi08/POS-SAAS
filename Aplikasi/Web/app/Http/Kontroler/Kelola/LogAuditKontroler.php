<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Audit\Kueri\DaftarLogAudit;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Log audit tenant, hanya baca (aturan `LogAudit` §13.2, §25 no. 17).
 */
final class LogAuditKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarLogAudit $daftar, DaftarAnggota $anggota): Response
    {
        $kata = trim($permintaan->string('kata')->toString());
        $halaman = $daftar->Ambil($kata);
        $idPelaku = array_values(array_unique(array_filter(array_map(fn (LogAudit $log) => $log->IdPengguna, $halaman->items()))));
        $namaPelaku = $anggota->AmbilNamaPengguna($this->IdTenant(), $idPelaku);

        return Inertia::render('Kelola/LogAudit/Daftar', [
            'Log' => DaftarBerhalaman::Buat($halaman, fn (LogAudit $log): array => [
                'Id' => $log->Id,
                'Peristiwa' => $log->Peristiwa,
                'Pelaku' => $log->IdPengguna === null ? 'Sistem' : ($namaPelaku[$log->IdPengguna] ?? 'Pengguna lain'),
                'JenisObjek' => $log->JenisObjek,
                'IdObjek' => $log->IdObjek,
                'NilaiLama' => $log->NilaiLama,
                'NilaiBaru' => $log->NilaiBaru,
                'Ip' => $log->Ip,
                'DibuatPada' => $log->DibuatPada->toIso8601String(),
            ]),
            'Saring' => ['Kata' => $kata],
        ]);
    }
}
