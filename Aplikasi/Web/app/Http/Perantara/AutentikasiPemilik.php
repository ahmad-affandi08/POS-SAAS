<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Kueri\TokenPenggunaBerdasarkanToken;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TokenAksesPengguna;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi user token untuk `/api/pemilik/v1/*` (OWN-01, PRD §16): token Bearer dari `/masuk` yang belum dicabut dan
 * belum kedaluwarsa. Token tidak dikenal, dicabut, atau kedaluwarsa → 401 (aplikasi menganggap sesi berakhir). Menyimpan
 * token & pengguna di atribut request dan mengisi konteks log audit. Tenant belum ditetapkan di sini (`X-Tenant` diurus
 * `IdentifikasiTenantPemilik`).
 */
final class AutentikasiPemilik
{
    public const ATRIBUT_TOKEN = 'TokenPemilik';

    public function __construct(
        private readonly TokenPenggunaBerdasarkanToken $cariToken,
        private readonly PencatatAudit $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $token = is_string($bearer) ? $this->cariToken->Cari($bearer) : null;

        if ($token === null) {
            return GalatApi::Buat('TokenTidakValid', 'Sesi berakhir. Masuk lagi dengan email dan kata sandi.', 401);
        }

        $this->audit->AturKonteks($token->IdPengguna, $request->ip(), $request->userAgent());
        $request->attributes->set(self::ATRIBUT_TOKEN, $token);

        return $next($request);
    }

    public static function AmbilToken(Request $request): TokenAksesPengguna
    {
        $token = $request->attributes->get(self::ATRIBUT_TOKEN);
        abort_unless($token instanceof TokenAksesPengguna, 401);

        return $token;
    }

    public static function AmbilPengguna(Request $request): Pengguna
    {
        return self::AmbilToken($request)->Pengguna;
    }
}
