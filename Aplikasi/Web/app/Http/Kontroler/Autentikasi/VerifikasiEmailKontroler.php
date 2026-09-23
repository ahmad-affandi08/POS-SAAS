<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Organisasi\Aksi\KirimVerifikasiEmail;
use App\Domain\Organisasi\Aksi\VerifikasiEmailPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verifikasi email dari tautan dan kirim ulang tautan (BR-00.5).
 */
final class VerifikasiEmailKontroler extends Kontroler
{
    public function Verifikasi(Request $permintaan, Pengguna $pengguna, string $hash, VerifikasiEmailPengguna $verifikasi): RedirectResponse
    {
        $verifikasi->Jalankan($pengguna, $hash);

        return redirect()->route($permintaan->user('web') === null ? 'masuk' : 'kelola.beranda')
            ->with('Kilat', 'Email Anda sudah terverifikasi.');
    }

    public function KirimUlang(Request $permintaan, KirimVerifikasiEmail $kirim): RedirectResponse
    {
        $pengguna = $permintaan->user('web');
        abort_unless($pengguna instanceof Pengguna, 403);
        $kunci = 'verifikasi-email:'.$pengguna->Id;

        if (RateLimiter::tooManyAttempts($kunci, 3)) {
            return back()->withErrors(['Umum' => 'Tautan sudah dikirim beberapa kali. Coba lagi dalam beberapa menit.']);
        }

        RateLimiter::hit($kunci, 600);
        $kirim->Jalankan($pengguna);

        return back()->with('Kilat', "Tautan verifikasi dikirim ulang ke {$pengguna->Email}.");
    }
}
