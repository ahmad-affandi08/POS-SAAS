<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Organisasi\Model\Pengguna;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-22: pengguna yang kata sandi awalnya dibuat admin tenant harus menggantinya sebelum memilih usaha atau membuka
 * back-office.
 */
final class WajibGantiKataSandiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna instanceof Pengguna && $pengguna->WajibGantiKataSandi) {
            return redirect()->route('kata-sandi.ganti')
                ->with('Kilat', 'Ganti kata sandi awal dari admin usaha Anda sebelum melanjutkan.');
        }

        return $next($request);
    }
}
