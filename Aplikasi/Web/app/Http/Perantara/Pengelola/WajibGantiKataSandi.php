<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-22: anggota yang kata sandi awalnya dibuat Super Admin harus menggantinya sebelum membuka apa pun (termasuk
 * aktivasi 2FA). Hanya halaman ganti kata sandi dan keluar yang boleh dibuka.
 */
final class WajibGantiKataSandi
{
    private const RUTE_BOLEH = ['pengelola.kata-sandi.ganti', 'pengelola.kata-sandi.simpan', 'pengelola.keluar'];

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if ($pengguna instanceof PenggunaPengelola
            && $pengguna->WajibGantiKataSandi
            && ! in_array($request->route()?->getName(), self::RUTE_BOLEH, true)) {
            return redirect()->route('pengelola.kata-sandi.ganti')
                ->with('Kilat', 'Ganti kata sandi awal dari Super Admin sebelum melanjutkan.');
        }

        return $next($request);
    }
}
