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
 * BR-02.3: perangkat yang dicabut ditolak (`PerangkatDicabut`, 403), kecuali `sinkron/kirim` selama
 * [HARI_PEMULIHAN] hari setelah dicabut (audit P0 F-01): outbox yang dibuat offline sebelum `DicabutPada` masih bisa
 * dikirim (per item diperiksa `PenjagaAsalItemSinkron`, ditandai untuk ditinjau), lalu aplikasi menghapus tokennya.
 */
final class AutentikasiPerangkat
{
    public const ATRIBUT = 'Perangkat';

    public const HARI_PEMULIHAN = 7;

    private const RUTE_PEMULIHAN = 'pos.sinkron.kirim';

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

        if ($perangkat->CekDicabut() && ! self::CekBolehPemulihan($request, $perangkat)) {
            return GalatApi::Buat('PerangkatDicabut', 'Perangkat ini sudah dicabut dari back-office. Hubungi pemilik atau manajer usaha.', 403, [
                'DicabutPada' => $perangkat->DicabutPada?->utc()->toIso8601ZuluString(),
            ]);
        }

        $versi = $request->header('X-Versi-Aplikasi');
        $outbox = $request->header('X-Outbox-Tertunda');
        $this->aktivitas->Catat(
            $perangkat,
            is_string($versi) ? trim($versi) : null,
            is_string($outbox) && ctype_digit(trim($outbox)) ? min((int) trim($outbox), 1_000_000) : null,
        );
        $this->audit->AturKonteks(null, $request->ip(), $request->userAgent(), $perangkat->Id);
        $request->attributes->set(self::ATRIBUT, $perangkat);

        return $next($request);
    }

    private static function CekBolehPemulihan(Request $request, Perangkat $perangkat): bool
    {
        return $request->routeIs(self::RUTE_PEMULIHAN)
            && $perangkat->DicabutPada !== null
            && $perangkat->DicabutPada->copy()->addDays(self::HARI_PEMULIHAN)->isFuture();
    }

    public static function AmbilPerangkat(Request $request): Perangkat
    {
        $perangkat = $request->attributes->get(self::ATRIBUT);
        abort_unless($perangkat instanceof Perangkat, 401);

        return $perangkat;
    }
}
