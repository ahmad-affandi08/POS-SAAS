<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Kueri\CariPelangganPos;
use App\Domain\Pelanggan\Kueri\SaldoPoinPos;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/pelanggan?kata=` (F-16a): cari pelanggan aktif tenant untuk dipilih kasir (perlu online; pelanggan
 * baru dibuat offline lewat item outbox `Pelanggan.Buat`). Kata < 3 karakter = daftar kosong. Nomor HP tersamar.
 * `GET /api/pos/v1/pelanggan/{uuidPelanggan}/poin` (F-16b): saldo poin terkini & aturan tukar sebelum kasir menukar
 * poin (§18.4: wajib online).
 */
final class PelangganKontroler extends Kontroler
{
    public function Cari(Request $permintaan, CariPelangganPos $cari): JsonResponse
    {
        $valid = $permintaan->validate(['kata' => ['nullable', 'string', 'max:100']]);

        return response()->json(['Pelanggan' => $cari->Cari((string) ($valid['kata'] ?? ''))]);
    }

    public function Poin(string $uuidPelanggan, SaldoPoinPos $saldo): JsonResponse
    {
        return response()->json($saldo->Ambil($uuidPelanggan)
            ?? throw new PelanggaranAturanBisnis('PelangganTidakDitemukan', 'Pelanggan tidak ditemukan atau sudah diarsipkan.', 'UuidPelanggan', 404));
    }
}
