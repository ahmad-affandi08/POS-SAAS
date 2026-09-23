<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Aksi\AturUlangKataSandi;
use App\Domain\Organisasi\Aksi\KirimTautanAturUlangKataSandi;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Autentikasi\AturUlangKataSandiPermintaan;
use App\Http\Permintaan\Autentikasi\LupaKataSandiPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lupa & atur ulang kata sandi akun tenant (F-00, BR-00.9). Pesan setelah meminta tautan selalu sama, terdaftar atau
 * tidak (§25 no. 18). Permintaan tautan dibatasi per IP dan per email; kiriman atur ulang dibatasi per IP.
 */
final class LupaKataSandiKontroler extends Kontroler
{
    public const BATAS_PERMINTAAN_PER_IP_PER_JAM = 10;

    public const BATAS_PERMINTAAN_PER_EMAIL_PER_JAM = 3;

    public const BATAS_ATUR_ULANG_PER_IP_PER_JAM = 10;

    public const PESAN_TERKIRIM = 'Jika email tersebut terdaftar, tautan untuk mengatur ulang kata sandi sudah kami kirim. Periksa kotak masuk dan folder spam.';

    public function TampilkanPermintaan(): Response
    {
        return Inertia::render('Autentikasi/LupaKataSandi', [
            'MenitBerlaku' => (int) config('auth.passwords.users.expire'),
        ]);
    }

    public function KirimTautan(LupaKataSandiPermintaan $permintaan, KirimTautanAturUlangKataSandi $kirim): RedirectResponse
    {
        $email = mb_strtolower(trim($permintaan->string('Email')->toString()));
        $kunciIp = 'lupa-kata-sandi-ip:'.$permintaan->ip();
        $kunciEmail = 'lupa-kata-sandi-email:'.hash('sha256', $email);

        if (RateLimiter::tooManyAttempts($kunciIp, self::BATAS_PERMINTAAN_PER_IP_PER_JAM)) {
            throw new PelanggaranAturanBisnis('TerlaluBanyakPercobaan', 'Terlalu banyak permintaan dari jaringan ini. Coba lagi dalam '.$this->FormatMenit(RateLimiter::availableIn($kunciIp)).'.', 'Email');
        }

        RateLimiter::hit($kunciIp, 3600);

        // Batas per email tidak ditampilkan sebagai galat agar tidak membedakan email terdaftar atau tidak.
        if (! RateLimiter::tooManyAttempts($kunciEmail, self::BATAS_PERMINTAAN_PER_EMAIL_PER_JAM)) {
            RateLimiter::hit($kunciEmail, 3600);
            $kirim->Jalankan($email);
        }

        return redirect()->route('lupa-kata-sandi')->with('Kilat', self::PESAN_TERKIRIM);
    }

    public function TampilkanAturUlang(Request $permintaan, string $token): Response
    {
        return Inertia::render('Autentikasi/AturUlangKataSandi', [
            'Token' => $token,
            'Email' => $permintaan->string('email')->toString(),
        ]);
    }

    public function AturUlang(AturUlangKataSandiPermintaan $permintaan, AturUlangKataSandi $aturUlang): RedirectResponse
    {
        $kunci = 'atur-ulang-kata-sandi-ip:'.$permintaan->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_ATUR_ULANG_PER_IP_PER_JAM)) {
            throw new PelanggaranAturanBisnis('TerlaluBanyakPercobaan', 'Terlalu banyak percobaan dari jaringan ini. Coba lagi dalam '.$this->FormatMenit(RateLimiter::availableIn($kunci)).'.');
        }

        RateLimiter::hit($kunci, 3600);

        $aturUlang->Jalankan(
            $permintaan->string('Email')->toString(),
            $permintaan->string('Token')->toString(),
            $permintaan->string('KataSandi')->toString(),
        );

        return redirect()->route('masuk')->with('Kilat', 'Kata sandi sudah diganti. Masuk dengan kata sandi baru Anda.');
    }

    private function FormatMenit(int $detik): string
    {
        return max(1, intdiv($detik + 59, 60)).' menit';
    }
}
