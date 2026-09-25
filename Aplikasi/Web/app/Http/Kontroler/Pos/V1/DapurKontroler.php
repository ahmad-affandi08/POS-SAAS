<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Kueri\DaftarStasiunDapur;
use App\Domain\Pemenuhan\Aksi\UbahStatusTiketDapur;
use App\Domain\Pemenuhan\Enum\StatusTiketDapur;
use App\Domain\Pemenuhan\Kueri\TiketDapurOutlet;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * KDS (F-10b fase 1, online): `GET /api/pos/v1/dapur/tiket?stasiun[]=` tiket aktif outlet perangkat (tanpa `stasiun`
 * = semua stasiun), `POST /api/pos/v1/dapur/tiket/{uuid}/status {Status}` satu langkah maju/mundur.
 */
final class DapurKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, TiketDapurOutlet $kueri, DaftarStasiunDapur $stasiun): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate(['stasiun' => ['sometimes', 'array', 'max:20'], 'stasiun.*' => ['string', 'ulid']]);
        $idPerUuid = $stasiun->AmbilIdAktifPerUuid();
        $pilihan = isset($valid['stasiun']) ? array_values(array_filter(array_map(fn (mixed $u): ?int => $idPerUuid[strtoupper((string) $u)] ?? null, (array) $valid['stasiun']))) : null;
        $uuidPerId = array_flip($idPerUuid);

        return response()->json([
            'Tiket' => array_map(function (array $t) use ($uuidPerId): array {
                $t['UuidStasiun'] = $uuidPerId[$t['IdStasiunDapur']] ?? null;
                unset($t['IdStasiunDapur']);

                return $t;
            }, $kueri->AmbilUntukKds($perangkat->IdOutlet, $pilihan)),
            'WaktuServer' => now()->toIso8601ZuluString(),
        ]);
    }

    public function UbahStatus(Request $permintaan, string $tiketDapur, TiketDapurOutlet $kueri, UbahStatusTiketDapur $ubah): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate(['Status' => ['required', 'string', Rule::enum(StatusTiketDapur::class)]]);
        $tiket = $kueri->CariDiOutlet(strtoupper($tiketDapur), $perangkat->IdOutlet)
            ?? throw new PelanggaranAturanBisnis('TiketTidakDitemukan', 'Tiket dapur tidak ditemukan di outlet ini.', 'Uuid', 404);
        $hasil = $ubah->Jalankan($tiket, StatusTiketDapur::from((string) $valid['Status']));

        return response()->json(['Uuid' => $hasil->Uuid, 'Status' => $hasil->Status->value]);
    }
}
