<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

/**
 * Kunci sesi Platform Pengelola. Disimpan di cookie sesi pengelola yang terpisah dari tenant (BR-P01.4).
 */
final class SesiPengelola
{
    public const DUA_FAKTOR_TERVERIFIKASI = 'Pengelola.DuaFaktorTerverifikasi';

    public const TERAKHIR_AKTIF = 'Pengelola.TerakhirAktifPada';

    public const RAHASIA_2FA_SEMENTARA = 'Pengelola.Rahasia2faSementara';

    public const KODE_PEMULIHAN_BARU = 'Pengelola.KodePemulihanBaru';

    public const GUARD = 'pengelola';
}
