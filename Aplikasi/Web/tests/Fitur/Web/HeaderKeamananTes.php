<?php

declare(strict_types=1);

use App\Http\Perantara\PasangHeaderKeamanan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Audit F-14: header keamanan dipasang aplikasi di halaman web & API; HSTS hanya lewat HTTPS.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('halaman masuk & API punya header keamanan; HSTS hanya di HTTPS', function (): void {
    $respons = $this->get('/masuk')->assertOk();

    expect($respons->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respons->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($respons->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($respons->headers->get('Content-Security-Policy'))->toBe(PasangHeaderKeamanan::CSP)
        ->and($respons->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'")
        ->and($respons->headers->get('Permissions-Policy'))->toContain('microphone=()')
        ->and($respons->headers->has('Strict-Transport-Security'))->toBeFalse();

    $this->get('https://localhost/masuk')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->getJson('/api/pos/v1/konfigurasi-aplikasi')->assertUnauthorized()->assertHeader('X-Content-Type-Options', 'nosniff');
});
