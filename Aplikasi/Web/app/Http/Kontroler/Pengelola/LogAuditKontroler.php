<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response;

/**
 * Log audit Platform Pengelola, hanya baca (BR-P01.3, TabelData D-16): cari sebagian nama aksi, saring rentang
 * tanggal; bawaan terbaru di atas. Kueri sengaja di kontroler ini: test arsitektur membatasi pembaca
 * `LogAuditPengelola` hanya ke halaman log audit.
 */
final class LogAuditKontroler extends Kontroler
{
    private const KOLOM_URUT = ['DibuatPada', 'Aksi'];

    private const KOLOM_SARING = ['Tanggal'];

    public function Daftar(Request $permintaan): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), self::KOLOM_URUT, '-DibuatPada', self::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/LogAudit/Daftar', 'Log', fn (): array => $this->AmbilTabel($tabel));
    }

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    private function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $tanggal = $permintaan->AmbilRentangTanggal('Tanggal');
        $kueri = LogAuditPengelola::query()
            ->with('Pelaku:Id,Nama,Email')
            ->when($permintaan->cari !== '', fn ($kueri) => $kueri->where('Aksi', 'like', PenerapKueriTabel::PolaCari($permintaan->cari)))
            ->when($tanggal['Dari'] !== null, fn ($kueri) => $kueri->where('DibuatPada', '>=', $tanggal['Dari'].' 00:00:00'))
            ->when($tanggal['Sampai'] !== null, fn ($kueri) => $kueri->where('DibuatPada', '<=', $tanggal['Sampai'].' 23:59:59'));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'Id', 'Aksi' => 'Aksi'], fn (Collection $log): array => array_values($log->map(fn (LogAuditPengelola $l): array => [
            'Id' => $l->Id,
            'Aksi' => $l->Aksi,
            'Pelaku' => $l->Pelaku->Nama ?? 'Sistem',
            'JenisObjek' => $l->JenisObjek,
            'IdObjek' => $l->IdObjek,
            'IdTenant' => $l->IdTenant,
            'NilaiLama' => $l->NilaiLama,
            'NilaiBaru' => $l->NilaiBaru,
            'Alasan' => $l->Alasan,
            'Ip' => $l->Ip,
            'DibuatPada' => $l->DibuatPada->toIso8601String(),
        ])->all()));
    }
}
