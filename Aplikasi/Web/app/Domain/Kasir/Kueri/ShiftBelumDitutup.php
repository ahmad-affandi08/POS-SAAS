<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonInterface;

/**
 * Shift yang belum tertutup (terbuka, sedang menutup, dibuka ulang) sampai tanggal bisnis tertentu, untuk syarat tutup
 * buku F-15: periode/hari tidak boleh ditutup selama kasnya belum direkonsiliasi.
 */
final class ShiftBelumDitutup
{
    /**
     * @param  int|null  $idOutlet  null = semua outlet tenant
     */
    public function Hitung(CarbonInterface $sampaiTanggal, ?int $idOutlet = null, ?CarbonInterface $dariTanggal = null): int
    {
        return Shift::query()
            ->whereIn('Status', [StatusShift::Terbuka->value, StatusShift::Menutup->value, StatusShift::DibukaUlang->value])
            ->whereDate('TanggalBisnis', '<=', $sampaiTanggal->toDateString())
            ->when($dariTanggal !== null, fn ($k) => $k->whereDate('TanggalBisnis', '>=', $dariTanggal?->toDateString()))
            ->when($idOutlet !== null, fn ($k) => $k->where('IdOutlet', $idOutlet))
            ->count();
    }
}
