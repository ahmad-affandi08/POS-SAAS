<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Layanan\PencatatAuditAkun;
use App\Domain\Organisasi\Layanan\PenentuWajibDuaFaktor;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Menonaktifkan 2FA akun tenant dengan konfirmasi kata sandi (§20.2, BR-00.8). 2FA milik akun, bukan tenant, jadi
 * ditolak bila tenant mana pun tempat pengguna menjadi anggota aktif mewajibkan 2FA untuk perannya (bukan hanya
 * tenant aktif). Dicatat di `LogAudit` setiap tenant anggota.
 */
final class NonaktifkanDuaFaktorPengguna
{
    public function __construct(
        private readonly PenentuWajibDuaFaktor $penentuWajib,
        private readonly PencatatAuditAkun $auditAkun,
    ) {}

    public function Jalankan(Pengguna $pengguna, string $kataSandi): void
    {
        if ($this->penentuWajib->AmbilTenantWajib($pengguna->Id) !== []) {
            throw new PelanggaranAturanBisnis(
                'BR-00.8',
                'Verifikasi dua langkah tidak bisa dinonaktifkan karena paket langganan usaha tempat Anda bergabung mewajibkannya untuk peran Anda.',
            );
        }

        if (! Hash::check($kataSandi, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiSalah', 'Kata sandi salah.', 'KataSandi');
        }

        if (! $pengguna->CekDuaFaktorAktif()) {
            return;
        }

        DB::transaction(function () use ($pengguna): void {
            $pengguna->forceFill(['Rahasia2fa' => null, 'KodePemulihan2fa' => null, 'DuaFaktorAktifPada' => null])->save();
            $this->auditAkun->Catat('akun.dua-faktor-nonaktif', $pengguna);
        });
    }
}
