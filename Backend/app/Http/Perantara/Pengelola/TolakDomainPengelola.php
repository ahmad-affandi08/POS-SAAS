<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute tenant tidak dilayani di subdomain pengelola (PRD §13.8): akses di sana dijawab 404.
 */
final class TolakDomainPengelola
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->getHost() === config('pengelola.Domain'), 404);

        return $next($request);
    }
}
