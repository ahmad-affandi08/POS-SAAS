<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di rute: `->middleware(WajibIzinPengelola::class.':tim.anggota.undang')` (PRD §19.3).
 */
final class WajibIzinPengelola
{
    public function handle(Request $request, Closure $next, string $izin): Response
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        abort_unless(
            $pengguna instanceof PenggunaPengelola && $pengguna->PunyaIzin(IzinPengelola::from($izin)),
            403,
            'Anda tidak punya izin untuk membuka halaman ini.',
        );

        return $next($request);
    }
}
