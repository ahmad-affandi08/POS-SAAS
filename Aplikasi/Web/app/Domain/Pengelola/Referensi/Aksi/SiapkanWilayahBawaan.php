<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Data\DataWilayah;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wilayah awal P-02 (38 provinsi + 514 kabupaten/kota, Kepmendagri 2025) dari file data `database/Data/WilayahAwal.json`.
 * Data awal wajib: tanpa wilayah, pendaftaran tenant tidak bisa memilih kota (F-00). Idempoten: kode yang sudah ada
 * tidak diubah (koreksi lewat layar Referensi Wilayah atau `pengelola:impor-wilayah`). Semua baris valid atau tidak ada
 * yang tersimpan.
 */
final class SiapkanWilayahBawaan
{
    /**
     * @return int jumlah wilayah baru
     */
    public function Jalankan(?string $pathData = null): int
    {
        $daftar = self::BacaData($path = $pathData ?? database_path('Data/WilayahAwal.json'));
        $provinsiDiBerkas = [];

        foreach ($daftar as $data) {
            if ($data->tingkat === TingkatWilayah::Provinsi) {
                $provinsiDiBerkas[$data->kode] = true;
            }
        }

        foreach ($daftar as $data) {
            try {
                SimpanWilayah::PastikanValid($data, indukBoleh: isset($provinsiDiBerkas[(string) $data->kodeInduk]));
            } catch (PelanggaranAturanBisnis $galat) {
                throw new RuntimeException("Wilayah {$data->kode} di {$path} tidak valid: {$galat->getMessage()}");
            }
        }

        return DB::transaction(function () use ($daftar): int {
            $kodeAda = array_flip(Wilayah::query()->pluck('Kode')->all());
            $baru = 0;

            foreach ($daftar as $data) {
                if (isset($kodeAda[$data->kode])) {
                    continue;
                }

                Wilayah::query()->create($data->KeLarik());
                $baru++;
            }

            return $baru;
        });
    }

    /**
     * @return list<DataWilayah> urut Kode (provinsi sebelum kabupaten/kotanya)
     */
    private static function BacaData(string $path): array
    {
        $isi = is_readable($path) ? file_get_contents($path) : false;
        $data = $isi === false ? null : json_decode($isi, true);

        if (! is_array($data) || ! isset($data['Wilayah']) || ! is_array($data['Wilayah'])) {
            throw new RuntimeException("File data wilayah awal tidak valid: {$path}");
        }

        $hasil = [];
        $kodeTerlihat = [];

        foreach ($data['Wilayah'] as $baris) {
            $tingkat = is_array($baris) && is_string($baris['Tingkat'] ?? null) ? TingkatWilayah::tryFrom($baris['Tingkat']) : null;
            $zona = is_array($baris) && is_string($baris['ZonaWaktu'] ?? null) ? ZonaWaktu::tryFrom($baris['ZonaWaktu']) : null;
            $kodeInduk = is_array($baris) ? ($baris['KodeInduk'] ?? null) : null;

            if (! is_array($baris) || $tingkat === null || $zona === null || ! is_string($baris['Kode'] ?? null)
                || ! is_string($baris['Nama'] ?? null) || trim($baris['Nama']) === ''
                || ($kodeInduk !== null && ! is_string($kodeInduk))) {
                throw new RuntimeException("Baris wilayah awal tidak lengkap di {$path}.");
            }

            if (isset($kodeTerlihat[$baris['Kode']])) {
                throw new RuntimeException("Kode wilayah {$baris['Kode']} ganda di {$path}.");
            }

            $kodeTerlihat[$baris['Kode']] = true;
            $hasil[] = new DataWilayah($baris['Kode'], trim($baris['Nama']), $tingkat, $kodeInduk, $zona);
        }

        usort($hasil, fn (DataWilayah $a, DataWilayah $b) => strcmp($a->kode, $b->kode));

        return $hasil;
    }
}
