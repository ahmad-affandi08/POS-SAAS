<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Pengelola\TimInternal\Kueri\DaftarLogAuditPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Log audit Platform Pengelola, hanya baca (BR-P01.3, TabelData D-16).
 */
final class LogAuditKontroler extends Kontroler
{
    public function Daftar(Request $permintaan, DaftarLogAuditPengelola $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarLogAuditPengelola::KOLOM_URUT, '-DibuatPada', DaftarLogAuditPengelola::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/LogAudit/Daftar', 'Log', fn (): array => $daftar->AmbilTabel($tabel));
    }
}
