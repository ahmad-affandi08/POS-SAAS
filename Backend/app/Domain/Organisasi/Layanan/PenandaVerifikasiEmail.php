<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\Pengguna;

/**
 * Hash email pada tautan verifikasi (BR-00.5): tautan lama tidak berlaku lagi bila email diganti.
 */
final class PenandaVerifikasiEmail
{
    public function BuatHash(Pengguna $pengguna): string
    {
        return hash('sha256', mb_strtolower($pengguna->Email));
    }

    public function CekCocok(Pengguna $pengguna, string $hash): bool
    {
        return hash_equals($this->BuatHash($pengguna), $hash);
    }
}
