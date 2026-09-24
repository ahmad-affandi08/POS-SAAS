<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use Illuminate\Support\Collection;

/**
 * Log audit Platform Pengelola untuk `TabelData` (D-16, BR-P01.3, hanya baca): cari sebagian nama aksi, saring
 * rentang tanggal; bawaan terbaru di atas.
 */
final class DaftarLogAuditPengelola
{
    public const KOLOM_URUT = ['DibuatPada', 'Aksi'];

    public const KOLOM_SARING = ['Tanggal'];

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
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
