<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Perangkat;

/**
 * Pemakaian batas `BatasPerangkatPerOutlet` (BR-02.1): perangkat yang belum dicabut di satu outlet (termasuk yang
 * belum diaktifkan, karena kodenya sudah bisa ditukar kapan saja).
 */
final class PemakaianPerangkat
{
    public function HitungAktifDiOutlet(int $idOutlet): int
    {
        return Perangkat::query()->where('IdOutlet', $idOutlet)->whereNull('DicabutPada')->count();
    }
}
