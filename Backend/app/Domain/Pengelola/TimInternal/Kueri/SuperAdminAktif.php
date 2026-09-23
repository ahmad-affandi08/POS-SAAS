<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Kueri;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menghitung Super Admin aktif untuk BR-P01.1 (minimal 2 Super Admin aktif).
 */
final class SuperAdminAktif
{
    /** Dipanggil di dalam transaksi dengan `$kunci = true` agar dua penonaktifan bersamaan tidak lolos bersamaan. */
    public function Hitung(bool $kunci = false): int
    {
        $kueri = PenggunaPengelola::query()
            ->where('Aktif', true)
            ->whereHas('Peran', fn (Builder $peran) => $peran->where('Kode', PeranPengelolaBawaan::SuperAdmin->value));

        if ($kunci) {
            return $kueri->lockForUpdate()->count();
        }

        return $kueri->count();
    }
}
