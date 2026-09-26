<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Izin tenant untuk rute Aplikasi Owner (PRD §19.1, hak akses sama dengan back-office). Pemakaian:
 * `->middleware(WajibIzinPemilik::class.':laporan.penjualan.lihat')`, setelah `IdentifikasiTenantPemilik`.
 * Tanpa izin → 403 `TanpaIzin`.
 */
final class WajibIzinPemilik
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AksesPengguna $akses,
    ) {}

    public function handle(Request $request, Closure $next, string $izin): Response
    {
        $idTenant = $this->konteks->Ambil();
        $pengguna = AutentikasiPemilik::AmbilPengguna($request);

        if ($idTenant !== null && $this->akses->CekIzin($idTenant, $pengguna->Id, IzinTenant::from($izin))) {
            return $next($request);
        }

        return GalatApi::Buat('TanpaIzin', 'Peran Anda tidak punya akses ke data ini. Minta pemilik usaha menambahkan izinnya.', 403);
    }
}
