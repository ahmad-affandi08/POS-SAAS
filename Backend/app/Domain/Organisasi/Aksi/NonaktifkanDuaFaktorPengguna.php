<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\Hash;

/**
 * Menonaktifkan 2FA akun tenant dengan konfirmasi kata sandi (§20.2, BR-00.8). Pemanggil menolak lebih dulu bila
 * tenant aktif mewajibkan 2FA untuk pengguna ini.
 *
 * TODO F-02: catat `LogAudit` tenant (§25 no. 17) setelah tabelnya ada.
 */
final class NonaktifkanDuaFaktorPengguna
{
    public function Jalankan(Pengguna $pengguna, string $kataSandi): void
    {
        if (! Hash::check($kataSandi, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiSalah', 'Kata sandi salah.', 'KataSandi');
        }

        if (! $pengguna->CekDuaFaktorAktif()) {
            return;
        }

        $pengguna->forceFill(['Rahasia2fa' => null, 'KodePemulihan2fa' => null, 'DuaFaktorAktifPada' => null])->save();
    }
}
