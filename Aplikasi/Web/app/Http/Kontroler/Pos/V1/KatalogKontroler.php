<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Katalog\Harga\Kueri\KatalogPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\AmbilKatalogPermintaan;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/pos/v1/katalog?sejak={Kursor}` (F-03 D.3): katalog lengkap atau delta untuk tenant perangkat. Tenant dari
 * token perangkat; kursor tidak valid = 422 `KursorTidakValid` (POS lalu sinkron lengkap).
 */
final class KatalogKontroler extends Kontroler
{
    public function Ambil(AmbilKatalogPermintaan $permintaan, KatalogPos $katalog): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);

        return response()->json($katalog->Ambil($perangkat->IdOutlet, $permintaan->AmbilKursor()));
    }
}
