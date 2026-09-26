<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-20: pemisahan domain pemasaran (`DOMAIN_PEMASARAN`, misal `payou.id`) dan tenant (`DOMAIN_TENANT`, misal
 * `dashboard.payou.id`). Di domain pemasaran hanya rute pemasaran yang dilayani; rute lain dialihkan ke domain tenant
 * dengan jalur & query yang sama (GET 302, selain itu 307 agar metode & isi tetap). Beranda di domain tenant dialihkan
 * ke back-office (tamu diarahkan ke halaman masuk oleh `auth`). Tanpa kedua domain diatur, tidak melakukan apa-apa.
 */
final class ArahkanDomainAplikasi
{
    /** Rute yang tetap dilayani di domain pemasaran. Dokumen legal & kompatibilitas juga dilayani di domain tenant. */
    public const RUTE_PEMASARAN = ['beranda', 'legal.tampil', 'publik.kompatibilitas-perangkat'];

    public function handle(Request $request, Closure $next): Response
    {
        $pemasaran = self::AmbilDomain('domain.Pemasaran');
        $tenant = self::AmbilDomain('domain.Tenant');

        if ($pemasaran === null || $tenant === null || $pemasaran === $tenant) {
            return $next($request);
        }

        $rute = $request->route()?->getName();
        $host = strtolower($request->getHost());

        if ($host === $pemasaran && ! in_array($rute, self::RUTE_PEMASARAN, true)) {
            return redirect()->away(
                self::BuatUrl($tenant, $request->getRequestUri()),
                $request->isMethod('GET') || $request->isMethod('HEAD') ? 302 : 307,
            );
        }

        if ($host === $tenant && $rute === 'beranda') {
            return redirect()->route('kelola.beranda');
        }

        return $next($request);
    }

    /** Alamat [jalur] di domain tenant; jalur relatif bila domain tenant belum diatur (satu host). */
    public static function BuatUrlTenant(string $jalur): string
    {
        $tenant = self::AmbilDomain('domain.Tenant');

        return $tenant === null || self::AmbilDomain('domain.Pemasaran') === null ? $jalur : self::BuatUrl($tenant, $jalur);
    }

    /** Alamat [jalur] di domain pemasaran; jalur relatif bila domain pemasaran belum diatur (satu host). */
    public static function BuatUrlPemasaran(string $jalur): string
    {
        $pemasaran = self::AmbilDomain('domain.Pemasaran');

        return $pemasaran === null || self::AmbilDomain('domain.Tenant') === null ? $jalur : self::BuatUrl($pemasaran, $jalur);
    }

    private static function BuatUrl(string $host, string $jalur): string
    {
        $skema = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return (is_string($skema) && $skema !== '' ? $skema : 'https').'://'.$host.'/'.ltrim($jalur, '/');
    }

    private static function AmbilDomain(string $kunci): ?string
    {
        $nilai = config($kunci);

        return is_string($nilai) && trim($nilai) !== '' ? strtolower(trim($nilai)) : null;
    }
}
