<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Layanan\DuaFaktorPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Langkah kedua masuk untuk akun ber-2FA (§20.2, BR-00.8): kode TOTP 6 digit, atau kode pemulihan sekali pakai.
 * Kode TOTP yang sudah dipakai tidak bisa dipakai ulang selama masa berlakunya (mencegah replay).
 */
final class VerifikasiDuaFaktorPengguna
{
    /** Jendela verifikasi ±1 langkah 30 detik → kode hidup paling lama ±90 detik. */
    private const DETIK_TANDA_TERPAKAI = 120;

    public function __construct(private readonly DuaFaktorPengguna $duaFaktor) {}

    public function Jalankan(Pengguna $pengguna, string $kode): void
    {
        if (! $pengguna->CekDuaFaktorAktif() || $pengguna->Rahasia2fa === null) {
            throw new PelanggaranAturanBisnis('DuaFaktorBelumAktif', 'Verifikasi dua langkah belum aktif untuk akun ini.');
        }

        $kodeNormal = preg_replace('/\s+/', '', $kode) ?? '';

        if ($this->duaFaktor->VerifikasiKode($pengguna->Rahasia2fa, $kodeNormal)) {
            if (! Cache::add('dua-faktor-terpakai:'.$pengguna->Id.':'.$kodeNormal, true, self::DETIK_TANDA_TERPAKAI)) {
                throw new PelanggaranAturanBisnis('KodeDuaFaktorTerpakai', 'Kode ini baru saja dipakai. Tunggu kode berikutnya di aplikasi autentikator.', 'Kode');
            }

            return;
        }

        $kodePemulihan = Str::upper(trim($kode));
        $sisa = $pengguna->KodePemulihan2fa ?? [];
        $cocok = array_values(array_filter($sisa, fn (string $simpanan) => hash_equals($simpanan, $kodePemulihan)));

        if ($cocok !== []) {
            $pengguna->forceFill(['KodePemulihan2fa' => array_values(array_diff($sisa, $cocok))])->save();

            return;
        }

        throw new PelanggaranAturanBisnis('KodeDuaFaktorSalah', 'Kode tidak cocok. Masukkan kode terbaru dari aplikasi autentikator atau kode pemulihan.', 'Kode');
    }
}
