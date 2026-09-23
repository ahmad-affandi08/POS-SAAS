<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang setelah `auth:pengelola`:
 * - anggota yang dinonaktifkan langsung terputus di request berikutnya (P-01 langkah 6);
 * - sesi berakhir setelah 30 menit tidak aktif (BR-P01.2).
 */
final class PastikanPenggunaPengelola
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if (! $pengguna instanceof PenggunaPengelola) {
            abort(403);
        }

        $sesi = $request->session();
        $terakhirAktif = $sesi->get(SesiPengelola::TERAKHIR_AKTIF);
        $batasDetik = (int) config('pengelola.MenitSesiTidakAktif') * 60;
        $kedaluwarsa = is_int($terakhirAktif) && now()->getTimestamp() - $terakhirAktif > $batasDetik;

        if (! $pengguna->Aktif || $kedaluwarsa) {
            Auth::guard(SesiPengelola::GUARD)->logout();
            $sesi->invalidate();
            $sesi->regenerateToken();

            return redirect()->route('pengelola.masuk')->with(
                'Kilat',
                $pengguna->Aktif
                    ? 'Sesi berakhir karena tidak aktif selama 30 menit. Silakan masuk lagi.'
                    : 'Akun Anda sudah dinonaktifkan. Hubungi Super Admin bila ini keliru.',
            );
        }

        $sesi->put(SesiPengelola::TERAKHIR_AKTIF, now()->getTimestamp());

        return $next($request);
    }
}
