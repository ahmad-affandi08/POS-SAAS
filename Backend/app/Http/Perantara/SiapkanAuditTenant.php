<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi konteks log audit tenant (pelaku, IP, agen pengguna) untuk setiap request back-office, sehingga Aksi
 * domain menulis `LogAudit` tanpa mengenal objek Request (aturan `LogAudit` §13.2).
 */
final class SiapkanAuditTenant
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $idPengguna = Auth::guard('web')->id();
        $this->audit->AturKonteks(is_int($idPengguna) ? $idPengguna : null, $request->ip(), $request->userAgent());

        return $next($request);
    }
}
