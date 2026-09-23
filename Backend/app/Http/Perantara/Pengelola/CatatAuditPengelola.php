<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi konteks audit (pelaku & IP) untuk setiap request pengelola (BR-P01.3, PRD §13.8).
 * Aksi domain menulis LogAuditPengelola tanpa perlu mengenal objek Request.
 */
final class CatatAuditPengelola
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $idPelaku = Auth::guard(SesiPengelola::GUARD)->id();
        $this->audit->AturKonteks(is_int($idPelaku) ? $idPelaku : null, $request->ip());

        return $next($request);
    }
}
