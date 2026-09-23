<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Kueri\PerangkatBerdasarkanToken;
use App\Domain\Organisasi\Layanan\PencatatAktivitasPerangkat;
use App\Domain\Organisasi\Model\Perangkat;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi device token untuk `/api/pos/v1/*` (PRD §13.4, §16.1, F-02b). Menetapkan `KonteksTenant` dari token,
 * menyimpan perangkat di atribut request (`Perangkat`), mengisi konteks log audit, dan mencatat aktivitas
 * (`TerakhirAktifPada`, header `X-Versi-Aplikasi`).
 *
 * BR-02.3: perangkat yang dicabut langsung ditolak (`PerangkatDicabut`, 403). TODO F-07: `sinkron/kirim` tetap
 * menerima batch yang dibuat offline sebelum `DicabutPada` (dengan flag review); endpoint itu memeriksanya sendiri.
 */
final class AutentikasiPerangkat
{
    public const ATRIBUT = 'Perangkat';

    public function __construct(
        private readonly PerangkatBerdasarkanToken $cariPerangkat,
        private readonly PencatatAktivitasPerangkat $aktivitas,
        private readonly PencatatAudit $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $perangkat = is_string($token) ? $this->cariPerangkat->Cari($token) : null;

        if ($perangkat === null) {
            return GalatApi::Buat('TokenPerangkatTidakValid', 'Perangkat belum diaktifkan atau tokennya tidak berlaku. Aktifkan ulang dengan kode dari back-office.', 401);
        }

        if ($perangkat->CekDicabut()) {
            return GalatApi::Buat('PerangkatDicabut', 'Perangkat ini sudah dicabut dari back-office. Hubungi pemilik atau manajer usaha.', 403, [
                'DicabutPada' => $perangkat->DicabutPada?->utc()->toIso8601ZuluString(),
            ]);
        }

        $versi = $request->header('X-Versi-Aplikasi');
        $this->aktivitas->Catat($perangkat, is_string($versi) ? trim($versi) : null);
        $this->audit->AturKonteks(null, $request->ip(), $request->userAgent(), $perangkat->Id);
        $request->attributes->set(self::ATRIBUT, $perangkat);

        return $next($request);
    }

    public static function AmbilPerangkat(Request $request): Perangkat
    {
        $perangkat = $request->attributes->get(self::ATRIBUT);
        abort_unless($perangkat instanceof Perangkat, 401);

        return $perangkat;
    }
}
