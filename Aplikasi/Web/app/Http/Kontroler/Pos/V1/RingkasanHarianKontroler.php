<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Kueri\RingkasanAkhirHariPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * K-24: `GET /api/pos/v1/ringkasan-harian?tanggal=` ringkasan akhir hari outlet perangkat (semua perangkat; bawaan
 * tanggal bisnis hari ini, paling jauh 31 hari ke belakang).
 */
final class RingkasanHarianKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, RingkasanAkhirHariPos $kueri, TanggalBisnisOutlet $tanggal): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hariIni = $tanggal->Hitung($perangkat->IdOutlet);
        $valid = $permintaan->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hariIni->toDateString(), 'after_or_equal:'.$hariIni->subDays(31)->toDateString()],
        ]);

        return response()->json($kueri->Ambil($perangkat->IdOutlet, isset($valid['tanggal']) ? (string) $valid['tanggal'] : $hariIni->toDateString()));
    }
}
