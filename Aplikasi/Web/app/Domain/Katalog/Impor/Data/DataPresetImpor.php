<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Data;

/**
 * Preset format berkas impor (F-03, DesainF03 C.6) dari `resources/data/PresetImporProduk/{Kode}.json`, sudah
 * divalidasi `PembacaPresetImpor`. `kolom` = alias judul kolom per `BidangImpor`; `nilai*` = kata yang dikenali
 * (huruf kecil) per nilai tujuan. `modeVarian` ∈ Tidak, KolomInduk, BarisPerVarian.
 */
final readonly class DataPresetImpor
{
    public const MODE_VARIAN_TIDAK = 'Tidak';

    public const MODE_VARIAN_KOLOM_INDUK = 'KolomInduk';

    public const MODE_VARIAN_BARIS_PER_VARIAN = 'BarisPerVarian';

    /**
     * @param  array<string, list<string>>  $kolom
     * @param  array{Ya: list<string>, Tidak: list<string>}  $nilaiBoolean
     * @param  array<string, list<string>>  $nilaiJenis
     * @param  array<string, list<string>>  $nilaiKelompokPajak
     * @param  array<string, list<string>>  $nilaiStatus
     */
    public function __construct(
        public string $kode,
        public string $nama,
        public int $versiFormat,
        public bool $asumsi,
        public string $keterangan,
        public ?string $lembar,
        public ?int $barisJudul,
        public ?string $pemisahCsv,
        public array $kolom,
        public array $nilaiBoolean,
        public array $nilaiJenis,
        public array $nilaiKelompokPajak,
        public array $nilaiStatus,
        public string $modeVarian,
        public string $pemisahAtribut,
        public string $pemisahNamaNilai,
    ) {}
}
