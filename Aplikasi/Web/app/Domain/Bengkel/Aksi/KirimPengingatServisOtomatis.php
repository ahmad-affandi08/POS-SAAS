<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Aksi;

use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Bengkel\Tugas\KirimPengingatServisTugas;
use Carbon\CarbonImmutable;

/**
 * Putaran harian pengingat servis berkala (§9.10) satu tenant: perintah kerja yang sudah selesai/ditagih dengan tanggal
 * servis berikutnya dalam `HARI_SEBELUM` hari ke depan (H-3, termasuk hari ini), belum diingatkan, dan kendaraannya
 * belum datang lagi (tidak ada perintah kerja lebih baru), diantrekan satu tugas WhatsApp per perintah kerja.
 *
 * Pesan ini pesan layanan atas kendaraan yang diservis pelanggan sendiri (sejenis pengingat reservasi H-1), bukan
 * promosi, sehingga tidak memakai persetujuan pemasaran `Pelanggan.SetujuPemasaran`; staf mematikannya per perintah
 * kerja dengan mengosongkan tanggal servis berikutnya. Jadwalnya jam kerja pagi (09.10 WIB), di luar jam tenang.
 */
final class KirimPengingatServisOtomatis
{
    public const HARI_SEBELUM = 3;

    /** @return int jumlah pengingat diantrekan */
    public function Jalankan(int $idTenant, CarbonImmutable $hariIni): int
    {
        $id = PerintahKerja::query()
            ->whereIn('Status', [StatusPerintahKerja::Selesai->value, StatusPerintahKerja::Ditagih->value])
            ->whereBetween('ServisBerikutnyaPada', [$hariIni->toDateString(), $hariIni->addDays(self::HARI_SEBELUM)->toDateString()])
            ->whereNull('PengingatServisTerkirimPada')
            ->whereNull('PengingatServisDiprosesPada')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('PerintahKerja as Baru')
                ->whereColumn('Baru.IdTenant', 'PerintahKerja.IdTenant')
                ->whereColumn('Baru.IdKendaraan', 'PerintahKerja.IdKendaraan')
                ->whereColumn('Baru.Id', '>', 'PerintahKerja.Id')
                ->where('Baru.Status', '!=', StatusPerintahKerja::Dibatalkan->value))
            ->orderBy('Id')
            ->pluck('Id');

        foreach ($id as $idPerintahKerja) {
            KirimPengingatServisTugas::dispatch($idTenant, (int) $idPerintahKerja);
        }

        return $id->count();
    }
}
