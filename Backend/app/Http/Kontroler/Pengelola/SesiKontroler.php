<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Aksi\CatatMasukPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\Pengelola\SesiPengelola;
use App\Http\Permintaan\Pengelola\MasukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Masuk & keluar Platform Pengelola (P-01, BR-P01.2, BR-P01.4). Tidak ada halaman daftar publik.
 */
final class SesiKontroler extends Kontroler
{
    private const MAKS_PERCOBAAN = 5;

    public function TampilkanMasuk(): Response
    {
        return Inertia::render('Pengelola/Masuk');
    }

    public function Masuk(MasukPermintaan $permintaan, CatatMasukPengelola $catatMasuk): RedirectResponse
    {
        $email = Str::lower(trim($permintaan->string('Email')->toString()));
        $kunciBatas = 'pengelola-masuk:'.$email.'|'.$permintaan->ip();

        if (RateLimiter::tooManyAttempts($kunciBatas, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'Email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($kunciBatas).' detik.',
            ]);
        }

        $berhasil = Auth::guard(SesiPengelola::GUARD)->attempt([
            'Email' => $email,
            'password' => $permintaan->string('KataSandi')->toString(),
            'Aktif' => true,
        ]);

        if (! $berhasil) {
            RateLimiter::hit($kunciBatas);

            throw ValidationException::withMessages(['Email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($kunciBatas);

        $sesi = $permintaan->session();
        $sesi->regenerate();
        $sesi->forget(SesiPengelola::DUA_FAKTOR_TERVERIFIKASI);
        $sesi->put(SesiPengelola::TERAKHIR_AKTIF, now()->getTimestamp());

        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if ($pengguna instanceof PenggunaPengelola) {
            $catatMasuk->Jalankan($pengguna);
        }

        return redirect()->intended(route('pengelola.beranda'));
    }

    public function Keluar(Request $permintaan, PencatatAuditPengelola $audit): RedirectResponse
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if ($pengguna instanceof PenggunaPengelola) {
            $audit->Catat('sesi.keluar', $pengguna, idPelaku: $pengguna->Id);
        }

        Auth::guard(SesiPengelola::GUARD)->logout();
        $permintaan->session()->invalidate();
        $permintaan->session()->regenerateToken();

        return redirect()->route('pengelola.masuk');
    }
}
