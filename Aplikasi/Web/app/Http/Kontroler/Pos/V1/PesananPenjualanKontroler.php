<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Kueri\PesananPenjualanPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/pesanan-penjualan?kata=` (F-12 bagian 2): cari pre-order yang siap diambil di outlet perangkat
 * (perlu online). Pembuatan pre-order lewat item outbox `PesananPenjualan.Buat`; pengambilan lewat `Penjualan.Buat`
 * dengan `UuidPesananPenjualan`.
 */
final class PesananPenjualanKontroler extends Kontroler
{
    public function Cari(Request $permintaan, PesananPenjualanPos $cari): JsonResponse
    {
        $valid = $permintaan->validate(['kata' => ['nullable', 'string', 'max:100']]);

        return response()->json($cari->Cari(AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet, (string) ($valid['kata'] ?? '')));
    }
}
