<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\PemilikTenant;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Kueri\PersetujuanLegalTertunda;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-P06.5: Owner yang belum menyetujui versi materiil dokumen legal yang sudah berlaku diarahkan ke halaman
 * persetujuan sebelum membuka menu `/kelola` lain. Berjalan setelah `IdentifikasiTenantSesi`.
 */
final class WajibPersetujuanLegal
{
    public const RUTE_PERSETUJUAN = 'kelola.persetujuan-legal';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemilikTenant $pemilik,
        private readonly PersetujuanLegalTertunda $tertunda,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Auth::guard('web')->user();
        $idTenant = $this->konteks->Ambil();

        if ($request->routeIs(self::RUTE_PERSETUJUAN, self::RUTE_PERSETUJUAN.'.*')
            || ! $pengguna instanceof Pengguna
            || $idTenant === null
            || ! $this->pemilik->CekPemilik($pengguna->Id, $idTenant)
            || $this->tertunda->Ambil($idTenant, $pengguna->Id, now()) === []) {
            return $next($request);
        }

        // Hanya GET yang diingat sebagai tujuan lanjutan; kiriman formulir tidak bisa diulang lewat redirect.
        return $request->isMethod('GET')
            ? redirect()->guest(route(self::RUTE_PERSETUJUAN))
            : redirect()->route(self::RUTE_PERSETUJUAN);
    }
}
