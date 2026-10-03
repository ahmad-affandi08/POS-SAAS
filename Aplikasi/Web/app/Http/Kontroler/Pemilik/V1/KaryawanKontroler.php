<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Karyawan\Kueri\PantauKaryawan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pemilik/v1/karyawan` (OWN-10, izin `karyawan.lihat`): kehadiran hari ini (hadir, terlambat, belum masuk),
 * komisi & progres target bulan berjalan, dibatasi outlet akses pengguna.
 */
final class KaryawanKontroler extends DasarPemilikKontroler
{
    public function Pantau(Request $permintaan, PantauKaryawan $pantau): JsonResponse
    {
        return response()->json($pantau->Ambil($this->IdOutletBoleh($permintaan), CarbonImmutable::now()));
    }
}
