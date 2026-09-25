<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Aksi\KunciBayarPesananTerbuka;
use App\Domain\Penjualan\Kueri\PesananTerbukaOutlet;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pesanan terbuka outlet perangkat (F-07 mode meja fase 1): `GET /api/pos/v1/pesanan-terbuka` snapshot + ETag
 * (`If-None-Match` sama → 304), `POST|DELETE /api/pos/v1/pesanan-terbuka/{uuid}/kunci-bayar` kunci bayar online.
 * Perubahan pesanan sendiri tetap lewat outbox `sinkron/kirim`.
 */
final class PesananTerbukaKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, PesananTerbukaOutlet $kueri): JsonResponse|Response
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hasil = $kueri->Ambil($perangkat->IdOutlet);
        $etag = '"'.$hasil['Tanda'].'"';

        if (in_array($etag, $permintaan->getETags(), true)) {
            return response()->noContent(304)->setEtag($hasil['Tanda']);
        }

        return response()->json($hasil)->setEtag($hasil['Tanda']);
    }

    public function Kunci(Request $permintaan, string $pesananTerbuka, KunciBayarPesananTerbuka $kunci): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $sampai = $kunci->Kunci(strtoupper($pesananTerbuka), $perangkat->Id, $perangkat->IdOutlet);

        return response()->json(['KunciBayarSampai' => $sampai->toIso8601ZuluString()]);
    }

    public function Lepas(Request $permintaan, string $pesananTerbuka, KunciBayarPesananTerbuka $kunci): Response
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $kunci->Lepas(strtoupper($pesananTerbuka), $perangkat->Id, $perangkat->IdOutlet);

        return response()->noContent();
    }
}
