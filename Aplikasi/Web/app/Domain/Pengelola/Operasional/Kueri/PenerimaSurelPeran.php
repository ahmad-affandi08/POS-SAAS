<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Kueri;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alamat email anggota tim aktif per peran untuk alert & pemberitahuan (P-09, P-11). Peran dicoba berurutan:
 * peran pertama yang punya anggota aktif dipakai (misal Teknis, bila kosong Super Admin), sama seperti BR-P05.3.
 */
final class PenerimaSurelPeran
{
    /**
     * @return list<string>
     */
    public function Ambil(PeranPengelolaBawaan ...$urutanPeran): array
    {
        foreach ($urutanPeran as $peran) {
            $email = array_values(PenggunaPengelola::query()
                ->where('Aktif', true)
                ->whereHas('Peran', fn (Builder $kueri) => $kueri->where('Kode', $peran->value))
                ->orderBy('Id')
                ->pluck('Email')
                ->all());

            if ($email !== []) {
                return array_map('strval', $email);
            }
        }

        return [];
    }
}
