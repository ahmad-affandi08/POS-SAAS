<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Kueri;

use App\Domain\Pengelola\TimInternal\Model\UndanganPengelola;

final class UndanganBerlaku
{
    /** Undangan yang belum diterima, belum dibatalkan, dan belum lewat 48 jam; null bila tidak ada. */
    public function Cari(string $token, bool $kunci = false): ?UndanganPengelola
    {
        $kueri = UndanganPengelola::query()->where('HashToken', UndanganPengelola::HashDariToken($token));

        if ($kunci) {
            $kueri->lockForUpdate();
        }

        $undangan = $kueri->first();

        return $undangan?->MasihBerlaku() === true ? $undangan : null;
    }
}
