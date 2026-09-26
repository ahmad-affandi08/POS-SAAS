<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Kueri;

use App\Domain\Bersama\Tindakan\Model\LanggananRingkasanTindakan;

/** Status langganan ringkasan pagi Kotak Tindakan (D-23 D) pengguna di tenant aktif: tanpa pilihan = Owner saja. */
final class LanggananRingkasan
{
    public function CekAktif(int $idPengguna, bool $pemilik): bool
    {
        $aktif = LanggananRingkasanTindakan::query()->where('IdPengguna', $idPengguna)->value('Aktif');

        return $aktif === null ? $pemilik : (bool) $aktif;
    }
}
