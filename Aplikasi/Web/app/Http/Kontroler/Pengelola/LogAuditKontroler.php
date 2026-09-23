<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Log audit Platform Pengelola, hanya baca (BR-P01.3).
 */
final class LogAuditKontroler extends Kontroler
{
    private const PER_HALAMAN = 50;

    public function Daftar(Request $permintaan): Response
    {
        $kata = trim($permintaan->string('kata')->toString());

        $halaman = LogAuditPengelola::query()
            ->with('Pelaku:Id,Nama,Email')
            ->when($kata !== '', fn ($kueri) => $kueri->where('Aksi', 'like', '%'.addcslashes($kata, '%_\\').'%'))
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString()
            ->through(fn (LogAuditPengelola $log): array => [
                'Id' => $log->Id,
                'Aksi' => $log->Aksi,
                'Pelaku' => $log->Pelaku->Nama ?? 'Sistem',
                'JenisObjek' => $log->JenisObjek,
                'IdObjek' => $log->IdObjek,
                'IdTenant' => $log->IdTenant,
                'NilaiLama' => $log->NilaiLama,
                'NilaiBaru' => $log->NilaiBaru,
                'Alasan' => $log->Alasan,
                'Ip' => $log->Ip,
                'DibuatPada' => $log->DibuatPada->toIso8601String(),
            ]);

        return Inertia::render('Pengelola/LogAudit/Daftar', [
            'Log' => [
                'Data' => $halaman->items(),
                'HalamanSaatIni' => $halaman->currentPage(),
                'HalamanTerakhir' => $halaman->lastPage(),
                'Total' => $halaman->total(),
            ],
            'Saring' => ['Kata' => $kata],
        ]);
    }
}
