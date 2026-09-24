<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Kasir\Kueri\DataAwalKasir;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/data-awal` (PRD §16.3, F-06): data yang dibutuhkan aplikasi kasir untuk bekerja offline
 * (staf & PIN offline, kategori kas, pengaturan kasir). Diunduh saat aktivasi dan diperbarui berkala saat online.
 */
final class DataAwalKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, DataAwalKasir $dataAwal): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);

        return response()->json($dataAwal->Ambil($perangkat) + ['WaktuServer' => now()->utc()->toIso8601ZuluString()]);
    }
}
