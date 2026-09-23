<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;

/**
 * Mencatat waktu masuk terakhir setelah kata sandi benar (sebelum verifikasi 2FA).
 * Audit `sesi.masuk` ditulis setelah 2FA lolos oleh VerifikasiDuaFaktor.
 */
final class CatatMasukPengelola
{
    public function Jalankan(PenggunaPengelola $pengguna): void
    {
        $pengguna->forceFill(['TerakhirMasukPada' => now()])->saveQuietly();
    }
}
