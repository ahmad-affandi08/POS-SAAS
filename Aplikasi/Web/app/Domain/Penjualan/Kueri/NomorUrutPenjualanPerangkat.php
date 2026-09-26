<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Model\IsiDeposit;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\ReturPenjualan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Nomor urut penjualan terakhir satu perangkat per tanggal nomor (PRD v1.46 "Tindak lanjut tinjauan" (e)), untuk
 * `data-awal` `Perangkat.NomorUrutPenjualan`: aplikasi yang dipasang ulang melanjutkan sekuens BR-07.1
 * `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ}` tanpa memakai nomor yang sudah ada di server. `YYMMDD` & SEQ dibaca
 * dari `Nomor` (bukan dari `TanggalBisnis`) karena itulah yang dipakai perangkat. Hanya penjualan dengan tanggal
 * bisnis sejak `JUMLAH_HARI` hari terakhir (sekuens hari lama tidak dipakai lagi). `AmbilRetur` sama untuk nomor retur
 * `RJ/...` (`Perangkat.NomorUrutRetur`); F-16d: `AmbilIsiDeposit` untuk nomor isi deposit `DEP/...`
 * (`Perangkat.NomorUrutIsiDeposit`).
 */
final class NomorUrutPenjualanPerangkat
{
    public const JUMLAH_HARI = 14;

    /** `.../{YYMMDD}/{KodePerangkat}-{SEQ}` di akhir nomor. */
    private const POLA_NOMOR = '#/(\d{6})/[^/]+-(\d{1,9})$#';

    /**
     * Kunci `YYMMDD` menjadi bilangan bulat di larik PHP (kunci angka), tetapi tetap string di objek JSON.
     *
     * @return array<int|string, int> `YYMMDD` → nomor urut terbesar (> 0), urut tanggal
     */
    public function Ambil(int $idPerangkat, CarbonInterface $hariIni): array
    {
        return self::Petakan(Penjualan::query()
            ->where('IdPerangkat', $idPerangkat)
            ->where('TanggalBisnis', '>=', $hariIni->copy()->subDays(self::JUMLAH_HARI)->toDateString())
            ->pluck('Nomor'));
    }

    /**
     * Nomor urut retur penjualan terakhir perangkat ini per `YYMMDD` (nomor `RJ/{KodeOutlet}/{YYMMDD}/{Kode}-{SEQ}`).
     *
     * @return array<int|string, int>
     */
    public function AmbilRetur(int $idPerangkat, CarbonInterface $hariIni): array
    {
        return self::Petakan(ReturPenjualan::query()
            ->where('IdPerangkat', $idPerangkat)
            ->where('TanggalBisnis', '>=', $hariIni->copy()->subDays(self::JUMLAH_HARI)->toDateString())
            ->pluck('Nomor'));
    }

    /**
     * F-16d bagian 1: nomor urut isi deposit terakhir perangkat ini per `YYMMDD` (`DEP/{KodeOutlet}/{YYMMDD}/{Kode}-{SEQ}`).
     *
     * @return array<int|string, int>
     */
    public function AmbilIsiDeposit(int $idPerangkat, CarbonInterface $hariIni): array
    {
        return self::Petakan(IsiDeposit::query()
            ->where('IdPerangkat', $idPerangkat)
            ->where('TanggalBisnis', '>=', $hariIni->copy()->subDays(self::JUMLAH_HARI)->toDateString())
            ->pluck('Nomor'));
    }

    /**
     * @param  Collection<int, mixed>  $nomor
     * @return array<int|string, int>
     */
    private static function Petakan(Collection $nomor): array
    {
        $hasil = [];

        foreach ($nomor as $n) {
            if (preg_match(self::POLA_NOMOR, (string) $n, $cocok) !== 1) {
                continue;
            }

            $urut = (int) $cocok[2];

            if ($urut > 0 && $urut > ($hasil[$cocok[1]] ?? 0)) {
                $hasil[$cocok[1]] = $urut;
            }
        }

        ksort($hasil, SORT_STRING);

        return $hasil;
    }
}
