<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Perantara global (berjalan sebelum StartSession): request ke subdomain pengelola memakai cookie sesi sendiri,
 * sehingga sesi tenant dan sesi pengelola tidak pernah tercampur walau email sama (BR-P01.4, PRD §13.8).
 */
final class SiapkanSesiPengelola
{
    public function __construct(private readonly SessionManager $sesi) {}

    public function handle(Request $request, Closure $next): Response
    {
        $namaCookie = $request->getHost() === config('pengelola.Domain')
            ? config('pengelola.CookieSesi')
            : config('session.cookie');

        $this->sesi->driver()->setName((string) $namaCookie);

        return $next($request);
    }
}
