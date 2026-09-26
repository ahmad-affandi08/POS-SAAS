<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pemilik/v1/perangkat` (OWN-08, izin `perangkat.lihat`): status perangkat POS per outlet (terakhir aktif,
 * outbox tertunda, versi aplikasi), dibatasi outlet akses pengguna.
 */
final class PerangkatKontroler extends DasarPemilikKontroler
{
    public function Daftar(Request $permintaan, DaftarPerangkat $perangkat): JsonResponse
    {
        return response()->json(['Perangkat' => $perangkat->AmbilStatus($this->IdOutletBoleh($permintaan))]);
    }
}
