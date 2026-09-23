<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Referensi\Model\HariLibur;

/**
 * Hari libur terbit untuk dipakai tenant (forecast restock, jadwal kerja, laporan).
 */
final class HariLiburTerbit
{
    /**
     * @return list<HariLibur>
     */
    public function AmbilTahun(int $tahun): array
    {
        return array_values(HariLibur::query()
            ->whereYear('Tanggal', $tahun)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->orderBy('Tanggal')
            ->get()
            ->all());
    }

    public function CekSudahTerbit(int $tahun): bool
    {
        return HariLibur::query()->whereYear('Tanggal', $tahun)->where('Status', StatusDataMaster::Terbit->value)->exists();
    }
}
