<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Data\HasilItemSinkron;
use App\Domain\Bersama\Sinkron\Layanan\PemrosesSinkron;
use App\Domain\Penjualan\Kueri\PenjualanPerluTinjauan;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\KirimSinkronPermintaan;
use Illuminate\Http\JsonResponse;

/**
 * `POST /api/pos/v1/sinkron/kirim` (PRD §16.3, §18): menerima batch outbox perangkat dan membalas hasil per item
 * (`Diterima`/`Duplikat`/`Ditolak`) dalam urutan yang sama. F-06: `Shift.Buka`, `MutasiKas.Catat`; F-07b:
 * `Penjualan.Buat`; F-11: `Shift.Tutup`. `PerangkatDicabut` = true bila pengirim sudah dicabut (masa pemulihan, audit
 * P0 F-01): aplikasi mengosongkan outbox lalu menghapus tokennya. `PerluTinjauan` (K-17) = Uuid `Penjualan.Buat` di batch
 * ini yang diterima dengan tanda tinjauan back-office.
 */
final class SinkronKontroler extends Kontroler
{
    public function Kirim(KirimSinkronPermintaan $permintaan, PemrosesSinkron $pemroses, PenjualanPerluTinjauan $tinjauan): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $item = $permintaan->AmbilItem();
        $hasil = $pemroses->Proses($item, new DataKonteksSinkron($perangkat->IdTenant, $perangkat->Id, $perangkat->IdOutlet));
        $uuidPenjualan = array_values(array_map(fn (array $i): string => $i['Uuid'], array_filter($item, fn (array $i): bool => $i['Jenis'] === 'Penjualan.Buat')));

        return response()->json([
            'Hasil' => array_map(fn (HasilItemSinkron $satu): array => $satu->KeArray(), $hasil),
            'WaktuServer' => now()->utc()->toIso8601ZuluString(),
            'PerangkatDicabut' => $perangkat->CekDicabut(),
            'PerluTinjauan' => $tinjauan->AmbilUuid($uuidPenjualan),
        ]);
    }
}
