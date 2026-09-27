<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit F-14 (PRD §20): header keamanan di lapisan aplikasi untuk semua respons (tidak bergantung pada Cloudflare/web
 * server). CSP berisi arahan yang aman untuk seluruh halaman (bingkai, `<base>`, plugin); tujuan formulir & daftar sumber
 * skrip/gaya belum dikunci karena analitik situs (GA4/Meta Pixel setelah persetujuan) dan Vite. HSTS hanya lewat HTTPS.
 * Header yang sudah diatur respons (misal unduhan) tidak ditimpa.
 */
final class PasangHeaderKeamanan
{
    public const CSP = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    public const IZIN_FITUR = 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()';

    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);
        $header = $respons->headers;

        foreach ([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Permissions-Policy' => self::IZIN_FITUR,
            'Content-Security-Policy' => self::CSP,
        ] as $nama => $nilai) {
            if (! $header->has($nama)) {
                $header->set($nama, $nilai);
            }
        }

        if ($request->isSecure() && ! $header->has('Strict-Transport-Security')) {
            $header->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respons;
    }
}
