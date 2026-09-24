<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Data\HasilItemSinkron;
use App\Domain\Bersama\Sinkron\Layanan\PemrosesSinkron;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\KirimSinkronPermintaan;
use Illuminate\Http\JsonResponse;

/**
 * `POST /api/pos/v1/sinkron/kirim` (PRD §16.3, §18): menerima batch outbox perangkat dan membalas hasil per item
 * (`Diterima`/`Duplikat`/`Ditolak`) dalam urutan yang sama. F-06: `Shift.Buka`, `MutasiKas.Catat`.
 */
final class SinkronKontroler extends Kontroler
{
    public function Kirim(KirimSinkronPermintaan $permintaan, PemrosesSinkron $pemroses): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hasil = $pemroses->Proses($permintaan->AmbilItem(), new DataKonteksSinkron($perangkat->IdTenant, $perangkat->Id, $perangkat->IdOutlet));

        return response()->json([
            'Hasil' => array_map(fn (HasilItemSinkron $satu): array => $satu->KeArray(), $hasil),
            'WaktuServer' => now()->utc()->toIso8601ZuluString(),
        ]);
    }
}
