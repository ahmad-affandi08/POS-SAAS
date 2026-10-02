<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Laporan\Model\LanggananInsightMingguan;

/** Status langganan insight mingguan X6 pengguna di tenant aktif: tanpa pilihan = Owner saja. */
final class LanggananInsight
{
    public function CekAktif(int $idPengguna, bool $pemilik): bool
    {
        $aktif = LanggananInsightMingguan::query()->where('IdPengguna', $idPengguna)->value('Aktif');

        return $aktif === null ? $pemilik : (bool) $aktif;
    }
}
