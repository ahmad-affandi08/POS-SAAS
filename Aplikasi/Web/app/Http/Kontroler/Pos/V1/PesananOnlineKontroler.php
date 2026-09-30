<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Aksi\TautkanPenjualanPesananOnline;
use App\Domain\Penjualan\Kueri\PesananOnlineOutlet;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PesananOnlineKontroler extends Kontroler
{
    public function Ambil(Request $request, PesananOnlineOutlet $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);

        return response()->json($kueri->AmbilAktif($perangkat->IdOutlet));
    }

    public function Tautkan(Request $request, string $pesananOnline, TautkanPenjualanPesananOnline $tautkan): JsonResponse
    {
        $valid = $request->validate(['UuidPenjualan' => ['required', 'ulid']]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);
        $pesanan = $tautkan->Jalankan($perangkat->IdOutlet, strtoupper($pesananOnline), strtoupper((string) $valid['UuidPenjualan']));

        return response()->json(['Uuid' => $pesanan->Uuid, 'Status' => $pesanan->Status->value]);
    }
}
