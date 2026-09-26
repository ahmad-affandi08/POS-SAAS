<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\Cache;

/**
 * OWN-01 + BR-00.8: "masuk tertunda" Aplikasi Owner untuk akun ber-2FA. Setelah kata sandi benar, aplikasi menerima
 * `TokenTantangan` acak (bukan token akses) yang berlaku `MENIT_BERLAKU` menit dan sekali pakai; server hanya
 * menyimpan hash-nya di cache. Kode TOTP/pemulihan lalu ditukar dengan token akses (`/masuk/dua-faktor`).
 */
final class PenyimpanTantanganDuaFaktor
{
    public const MENIT_BERLAKU = 5;

    public function Buat(Pengguna $pengguna): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Cache::put(self::Kunci($token), $pengguna->Id, now()->addMinutes(self::MENIT_BERLAKU));

        return $token;
    }

    /** Pengguna pemilik tantangan yang masih berlaku dan masih ber-2FA; null bila tidak dikenal/kedaluwarsa. */
    public function Ambil(string $token): ?Pengguna
    {
        if (preg_match('/^[A-Za-z0-9_-]{40,200}$/', $token) !== 1) {
            return null;
        }

        $idPengguna = Cache::get(self::Kunci($token));
        $pengguna = is_int($idPengguna) ? Pengguna::query()->find($idPengguna) : null;

        return $pengguna instanceof Pengguna && $pengguna->CekDuaFaktorAktif() ? $pengguna : null;
    }

    public function Hapus(string $token): void
    {
        Cache::forget(self::Kunci($token));
    }

    private static function Kunci(string $token): string
    {
        return 'pemilik-tantangan-2fa:'.hash('sha256', $token);
    }
}
