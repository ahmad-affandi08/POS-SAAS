<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Laporan\Kueri\LaporanRingkasPemilik;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Http\Permintaan\Pemilik\V1\LaporanPenjualanPermintaan;
use App\Http\Permintaan\Pemilik\V1\ShiftPermintaan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Laporan ringkas Aplikasi Owner (OWN-05, izin `laporan.penjualan.lihat`, dibatasi outlet akses): penjualan per
 * kelompok dalam rentang maks. 31 hari, dan daftar shift satu tanggal bisnis beserta selisih kasnya.
 */
final class LaporanKontroler extends DasarPemilikKontroler
{
    public const HARI_MAKS = 31;

    public function Penjualan(LaporanPenjualanPermintaan $permintaan, LaporanRingkasPemilik $laporan): JsonResponse
    {
        $dari = CarbonImmutable::createFromFormat('!Y-m-d', (string) $permintaan->string('dari'));
        $sampai = CarbonImmutable::createFromFormat('!Y-m-d', (string) $permintaan->string('sampai'));
        abort_unless($dari instanceof CarbonImmutable && $sampai instanceof CarbonImmutable, 422);

        if ($dari->diffInDays($sampai) + 1 > self::HARI_MAKS) {
            throw new PelanggaranAturanBisnis('RentangTerlaluPanjang', 'Rentang laporan paling panjang '.self::HARI_MAKS.' hari. Persempit tanggalnya.', 'sampai');
        }

        $idOutlet = $this->SaringOutlet($permintaan->query('outlet') === null ? null : (string) $permintaan->string('outlet'), $this->IdOutletBoleh($permintaan));

        return response()->json($laporan->Penjualan((string) $permintaan->string('kelompok'), new DataSaringLaporanPenjualan($dari, $sampai, $idOutlet)));
    }

    public function Shift(ShiftPermintaan $permintaan, LaporanRingkasPemilik $laporan): JsonResponse
    {
        $idOutlet = $this->SaringOutlet($permintaan->query('outlet') === null ? null : (string) $permintaan->string('outlet'), $this->IdOutletBoleh($permintaan));
        $tanggal = $this->BacaTanggal($permintaan->query('tanggal') === null ? null : (string) $permintaan->string('tanggal'), $idOutlet);

        return response()->json(['Shift' => $laporan->Shift($tanggal, $idOutlet)]);
    }
}
