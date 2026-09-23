<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Perantara\Pengelola\SesiPengelola;
use Illuminate\Support\Facades\Auth;

/**
 * Anggota tim yang sedang masuk (guard `pengelola`). Rute yang memakainya selalu di balik `auth:pengelola`.
 */
trait PelakuPengelola
{
    private function AmbilPelaku(): PenggunaPengelola
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();
        abort_unless($pengguna instanceof PenggunaPengelola, 403);

        return $pengguna;
    }
}
