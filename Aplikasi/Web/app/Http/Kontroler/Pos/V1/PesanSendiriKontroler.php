<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Aksi\TerimaPesananSendiri;
use App\Domain\Penjualan\Aksi\TolakPesananSendiri;
use App\Domain\Penjualan\Kueri\PesananSendiriOutlet;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\TerimaPesananSendiriPermintaan;
use App\Http\Permintaan\Pos\V1\TolakPesananSendiriPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-17 Self-Order QR Meja di POS: `GET /api/pos/v1/pesan-sendiri` (pesanan QR `MenungguKonfirmasi` outlet perangkat,
 * terlama dulu; ditarik berkala), `POST .../{uuid}/terima` `{UuidPengguna, UuidPesananTerbuka}` dan
 * `POST .../{uuid}/tolak` `{UuidPengguna, Alasan}`. Pelaku ber-izin `penjualan.buat` atau `pesanan.meja.catat`.
 * Keduanya idempoten menurut status (hasil sama bila diulang; diproses dengan hasil lain → 409 `SudahDiproses`).
 */
final class PesanSendiriKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, PesananSendiriOutlet $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);

        return response()->json(['Pesanan' => $kueri->AmbilMenunggu($perangkat->IdOutlet)]);
    }

    public function Terima(TerimaPesananSendiriPermintaan $permintaan, string $pesananSendiri, TerimaPesananSendiri $terima): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $pesanan = $terima->Jalankan(
            $perangkat->IdTenant,
            $perangkat->IdOutlet,
            $perangkat->Id,
            strtoupper($pesananSendiri),
            strtoupper($permintaan->string('UuidPengguna')->toString()),
            strtoupper($permintaan->string('UuidPesananTerbuka')->toString()),
        );

        return response()->json(['Uuid' => $pesanan->Uuid, 'Status' => $pesanan->Status->value, 'UuidPesananTerbuka' => $pesanan->UuidPesananTerbuka]);
    }

    public function Tolak(TolakPesananSendiriPermintaan $permintaan, string $pesananSendiri, TolakPesananSendiri $tolak): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $pesanan = $tolak->Jalankan(
            $perangkat->IdTenant,
            $perangkat->IdOutlet,
            $perangkat->Id,
            strtoupper($pesananSendiri),
            strtoupper($permintaan->string('UuidPengguna')->toString()),
            trim($permintaan->string('Alasan')->toString()),
        );

        return response()->json(['Uuid' => $pesanan->Uuid, 'Status' => $pesanan->Status->value]);
    }
}
