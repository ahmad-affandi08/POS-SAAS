<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Aksi\AntrekanKirimStrukDigital;
use App\Domain\Penjualan\Enum\KanalPesanKeluar;
use App\Domain\Penjualan\Kueri\HasilPesanKeluar;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\KirimStrukPermintaan;
use Illuminate\Http\JsonResponse;

/**
 * K3 kirim struk digital dari POS (wajib online):
 * - `POST /api/pos/v1/penjualan/{uuidPenjualan}/kirim-struk` `{Uuid, Kanal, Tujuan}` → 202 `{Uuid, Status}`; Uuid sama
 *   dikirim ulang → 200 dengan status terkini (idempoten, `Uuid` = kunci idempotensi).
 * - `GET /api/pos/v1/pesan-keluar/{uuid}` → `{Uuid, Status, PesanGalat}` untuk menampilkan hasil kiriman.
 */
final class PesanKeluarKontroler extends Kontroler
{
    public function KirimStruk(KirimStrukPermintaan $permintaan, string $uuidPenjualan, AntrekanKirimStrukDigital $antrekan): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hasil = $antrekan->Jalankan(
            $perangkat->IdTenant,
            $perangkat->IdOutlet,
            $perangkat->Id,
            strtoupper($uuidPenjualan),
            strtoupper($permintaan->string('Uuid')->toString()),
            KanalPesanKeluar::from($permintaan->string('Kanal')->toString()),
            $permintaan->string('Tujuan')->toString(),
        );

        return response()->json(
            ['Uuid' => $hasil['PesanKeluar']->Uuid, 'Status' => $hasil['PesanKeluar']->Status->value],
            $hasil['Baru'] ? 202 : 200,
        );
    }

    public function Tampilkan(string $uuidPesanKeluar, HasilPesanKeluar $kueri): JsonResponse
    {
        return response()->json($kueri->Ambil(strtoupper($uuidPesanKeluar))
            ?? throw new PelanggaranAturanBisnis('PesanKeluarTidakDitemukan', 'Kiriman struk tidak ditemukan.', 'Umum', 404));
    }
}
