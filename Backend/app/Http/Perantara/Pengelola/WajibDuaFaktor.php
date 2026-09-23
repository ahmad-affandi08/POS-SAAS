<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-P01.2 + AC P-01: tidak ada menu yang bisa dibuka sebelum 2FA aktif dan terverifikasi di sesi ini.
 */
final class WajibDuaFaktor
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if (! $pengguna instanceof PenggunaPengelola) {
            abort(403);
        }

        if (! $pengguna->DuaFaktorAktif()) {
            return redirect()->route('pengelola.dua-faktor.aktifkan')
                ->with('Kilat', 'Aktifkan verifikasi dua langkah sebelum membuka menu Platform Pengelola.');
        }

        if ($request->session()->get(SesiPengelola::DUA_FAKTOR_TERVERIFIKASI) !== true) {
            return redirect()->route('pengelola.dua-faktor.verifikasi');
        }

        return $next($request);
    }
}
