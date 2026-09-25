<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Pelanggan\Kueri\CariPelangganPos;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/pelanggan?kata=` (F-16a): cari pelanggan aktif tenant untuk dipilih kasir (perlu online; pelanggan
 * baru dibuat offline lewat item outbox `Pelanggan.Buat`). Kata < 3 karakter = daftar kosong. Nomor HP tersamar.
 */
final class PelangganKontroler extends Kontroler
{
    public function Cari(Request $permintaan, CariPelangganPos $cari): JsonResponse
    {
        $valid = $permintaan->validate(['kata' => ['nullable', 'string', 'max:100']]);

        return response()->json(['Pelanggan' => $cari->Cari((string) ($valid['kata'] ?? ''))]);
    }
}
