<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Aksi\BatalkanTagihanQrisPos;
use App\Domain\Penjualan\Aksi\BuatTagihanQrisPos;
use App\Domain\Penjualan\Aksi\CekStatusTagihanQrisPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\BuatTagihanQrisPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-08 QRIS dinamis (BR-08.5, wajib online): `POST /api/pos/v1/qris` membuat tagihan lewat gerbang aktif (201; Uuid
 * sama = 200 tagihan yang sama), `GET /api/pos/v1/qris/{uuid}` cek status (polling, cadangan webhook), `POST
 * /api/pos/v1/qris/{uuid}/batal` membatalkan tagihan yang belum dibayar. Hanya tagihan outlet perangkat (lainnya 404).
 */
final class TagihanQrisKontroler extends Kontroler
{
    public function Buat(BuatTagihanQrisPermintaan $permintaan, BuatTagihanQrisPos $buat): JsonResponse
    {
        [$tagihan, $baru] = $buat->Jalankan($permintaan->AmbilData(AutentikasiPerangkat::AmbilPerangkat($permintaan)));

        return response()->json($tagihan->KeLarikBuat(), $baru ? 201 : 200);
    }

    public function Status(Request $permintaan, string $tagihanQris, CekStatusTagihanQrisPos $cek): JsonResponse
    {
        return response()->json($cek->Jalankan($tagihanQris, AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet)->KeLarikStatus());
    }

    public function Batal(Request $permintaan, string $tagihanQris, BatalkanTagihanQrisPos $batal): JsonResponse
    {
        $tagihan = $batal->Jalankan($tagihanQris, AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet);

        return response()->json(['Uuid' => $tagihan->Uuid, 'Status' => $tagihan->Status->value]);
    }
}
