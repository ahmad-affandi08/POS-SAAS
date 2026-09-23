<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Kueri;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menghitung Super Admin aktif untuk BR-P01.1 (minimal 2 Super Admin aktif).
 */
final class SuperAdminAktif
{
    /**
     * Wajib menjadi pernyataan PERTAMA di transaksi yang bisa mengurangi jumlah Super Admin aktif.
     * Mengunci baris peran SuperAdmin sehingga perubahan seperti itu berjalan berurutan, dan snapshot
     * REPEATABLE READ baru terbentuk setelah kunci didapat (mencegah dua pencabutan bersamaan lolos).
     */
    public function KunciPerubahan(): void
    {
        PeranPengelola::query()->where('Kode', PeranPengelolaBawaan::SuperAdmin->value)->lockForUpdate()->first();
    }

    /** Dipanggil di dalam transaksi setelah KunciPerubahan(); `$kunci = true` juga mengunci baris pengguna yang dihitung. */
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
