<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\UndanganPengguna;

/**
 * Mencari undangan anggota dari token di tautan email. Hanya undangan yang belum diterima, belum dibatalkan, dan
 * belum kedaluwarsa yang dikembalikan; selain itu null (penyebab tidak dibedakan agar token tidak bisa ditebak).
 */
final class UndanganBerlaku
{
    public function Cari(string $token, bool $kunci = false): ?UndanganPengguna
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $undangan = UndanganPengguna::query()
            ->where('HashToken', UndanganPengguna::BuatHashToken($token))
            ->when($kunci, fn ($kueri) => $kueri->lockForUpdate())
            ->first();

        return $undangan !== null && $undangan->CekBerlaku() ? $undangan : null;
    }
}
