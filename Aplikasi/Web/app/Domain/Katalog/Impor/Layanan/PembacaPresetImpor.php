<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Data\DataPresetImpor;
use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use RuntimeException;

/**
 * Membaca & memvalidasi preset format impor (F-03, DesainF03 C.6) dari `resources/data/PresetImporProduk/*.json`.
 * Menambah aplikasi sumber = menambah satu berkas JSON. Preset `Umum` (templat bawaan) selalu pertama.
 * Skema: Kode (= nama berkas), Nama, VersiFormat 1, Asumsi (bool), Keterangan, Pembaca {Lembar, BarisJudul,
 * PemisahCsv ∈ , ; \t}, Kolom {BidangImpor: alias[]} (Nama wajib), Nilai {Boolean {Ya, Tidak}, Jenis
 * {JenisProduk: kata[]}, KelompokPajak {KategoriPajakProduk: kata[]}, Status {Aktif, Diarsipkan}}, Varian {Mode ∈
 * Tidak|KolomInduk|BarisPerVarian, KolomNamaInduk, PemisahAtribut, PemisahNamaNilai}.
 */
final class PembacaPresetImpor
{
    public const KODE_UMUM = 'Umum';

    /** @var array<string, DataPresetImpor>|null */
    private ?array $semua = null;

    public static function AmbilFolder(): string
    {
        return resource_path('data/PresetImporProduk');
    }

    /**
     * @return list<DataPresetImpor>
     */
    public function AmbilSemua(): array
    {
        if ($this->semua === null) {
            $hasil = [];

            foreach (glob(self::AmbilFolder().'/*.json') ?: [] as $path) {
                $kode = basename($path, '.json');
                $galat = self::Validasi(self::BacaJson($path), $kode);

                if ($galat !== []) {
                    throw new RuntimeException("Preset impor {$kode} tidak valid: ".implode('; ', $galat));
                }

                $hasil[$kode] = self::Bangun(self::BacaJson($path));
            }

            uksort($hasil, fn (string $a, string $b): int => [$a !== self::KODE_UMUM, $a] <=> [$b !== self::KODE_UMUM, $b]);
            $this->semua = $hasil;
        }

        return array_values($this->semua);
    }

    public function Cari(string $kode): ?DataPresetImpor
    {
        $this->AmbilSemua();

        return $this->semua[$kode] ?? null;
    }

    public function Ambil(string $kode): DataPresetImpor
    {
        return $this->Cari($kode) ?? $this->Cari(self::KODE_UMUM) ?? throw new RuntimeException('Preset impor Umum tidak ada.');
    }

    /**
     * @return list<string>
     */
    public function AmbilKode(): array
    {
        return array_map(fn (DataPresetImpor $preset): string => $preset->kode, $this->AmbilSemua());
    }

    /**
     * @return array<string, mixed>
     */
    public static function BacaJson(string $path): array
    {
        $isi = json_decode((string) file_get_contents($path), true);

        return is_array($isi) ? $isi : [];
    }

    /**
     * Galat skema preset (kosong = valid).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function Validasi(array $data, ?string $kodeBerkas = null): array
    {
        $galat = [];

        if (! is_string($data['Kode'] ?? null) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $data['Kode']) !== 1) {
            $galat[] = 'Kode wajib PascalCase';
        } elseif ($kodeBerkas !== null && $data['Kode'] !== $kodeBerkas) {
            $galat[] = 'Kode harus sama dengan nama berkas';
        }

        foreach (['Nama', 'Keterangan'] as $kunci) {
            if (! is_string($data[$kunci] ?? null) || trim($data[$kunci]) === '') {
                $galat[] = "{$kunci} wajib diisi";
            }
        }

        if (($data['VersiFormat'] ?? null) !== 1) {
            $galat[] = 'VersiFormat harus 1';
        }

        if (! is_bool($data['Asumsi'] ?? null)) {
            $galat[] = 'Asumsi wajib boolean';
        }

        $pembaca = $data['Pembaca'] ?? null;

        if (! is_array($pembaca)) {
            $galat[] = 'Pembaca wajib objek';
        } else {
            if (($pembaca['Lembar'] ?? null) !== null && ! is_string($pembaca['Lembar'])) {
                $galat[] = 'Pembaca.Lembar harus teks atau null';
            }

            if (($pembaca['BarisJudul'] ?? null) !== null && (! is_int($pembaca['BarisJudul']) || $pembaca['BarisJudul'] < 1 || $pembaca['BarisJudul'] > 10)) {
                $galat[] = 'Pembaca.BarisJudul harus 1–10 atau null';
            }

            if (($pembaca['PemisahCsv'] ?? null) !== null && ! in_array($pembaca['PemisahCsv'], [',', ';', "\t"], true)) {
                $galat[] = 'Pembaca.PemisahCsv harus , ; atau tab';
            }
        }

        $kolom = $data['Kolom'] ?? null;

        if (! is_array($kolom) || ! isset($kolom[BidangImpor::Nama->value])) {
            $galat[] = 'Kolom wajib objek dan memetakan Nama';
        } else {
            foreach ($kolom as $bidang => $alias) {
                if (BidangImpor::tryFrom((string) $bidang) === null) {
                    $galat[] = "Kolom {$bidang} bukan bidang impor";
                }

                if (! self::CekDaftarTeks($alias, true)) {
                    $galat[] = "Kolom {$bidang} harus daftar teks tidak kosong";
                }
            }
        }

        $nilai = $data['Nilai'] ?? null;

        if (! is_array($nilai)) {
            $galat[] = 'Nilai wajib objek';
        } else {
            $galat = [...$galat, ...self::ValidasiPeta($nilai['Boolean'] ?? null, ['Ya', 'Tidak'], 'Nilai.Boolean', true)];
            $galat = [...$galat, ...self::ValidasiPeta($nilai['Jenis'] ?? [], array_map(fn (JenisProduk $j): string => $j->value, JenisProduk::cases()), 'Nilai.Jenis', false)];
            $galat = [...$galat, ...self::ValidasiPeta($nilai['KelompokPajak'] ?? [], array_map(fn (KategoriPajakProduk $k): string => $k->value, KategoriPajakProduk::cases()), 'Nilai.KelompokPajak', false)];
            $galat = [...$galat, ...self::ValidasiPeta($nilai['Status'] ?? [], ['Aktif', 'Diarsipkan'], 'Nilai.Status', false)];
        }

        $varian = $data['Varian'] ?? null;

        if (! is_array($varian) || ! in_array($varian['Mode'] ?? null, [DataPresetImpor::MODE_VARIAN_TIDAK, DataPresetImpor::MODE_VARIAN_KOLOM_INDUK, DataPresetImpor::MODE_VARIAN_BARIS_PER_VARIAN], true)) {
            $galat[] = 'Varian.Mode harus Tidak, KolomInduk, atau BarisPerVarian';
        } else {
            foreach (['PemisahAtribut', 'PemisahNamaNilai'] as $kunci) {
                if (! is_string($varian[$kunci] ?? null) || $varian[$kunci] === '' || mb_strlen($varian[$kunci]) > 3) {
                    $galat[] = "Varian.{$kunci} wajib 1–3 karakter";
                }
            }

            if (! is_string($varian['KolomNamaInduk'] ?? null) || BidangImpor::tryFrom($varian['KolomNamaInduk']) === null) {
                $galat[] = 'Varian.KolomNamaInduk harus bidang impor';
            }
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $data  preset yang sudah valid
     */
    private static function Bangun(array $data): DataPresetImpor
    {
        $nilai = (array) $data['Nilai'];
        $pembaca = (array) $data['Pembaca'];
        $varian = (array) $data['Varian'];
        $boolean = self::KecilkanPeta((array) $nilai['Boolean']);

        return new DataPresetImpor(
            (string) $data['Kode'],
            (string) $data['Nama'],
            1,
            (bool) $data['Asumsi'],
            (string) $data['Keterangan'],
            is_string($pembaca['Lembar'] ?? null) ? $pembaca['Lembar'] : null,
            is_int($pembaca['BarisJudul'] ?? null) ? $pembaca['BarisJudul'] : null,
            is_string($pembaca['PemisahCsv'] ?? null) ? $pembaca['PemisahCsv'] : null,
            array_map(fn (mixed $alias): array => array_values(array_map('strval', (array) $alias)), (array) $data['Kolom']),
            ['Ya' => $boolean['Ya'] ?? [], 'Tidak' => $boolean['Tidak'] ?? []],
            self::KecilkanPeta((array) ($nilai['Jenis'] ?? [])),
            self::KecilkanPeta((array) ($nilai['KelompokPajak'] ?? [])),
            self::KecilkanPeta((array) ($nilai['Status'] ?? [])),
            (string) $varian['Mode'],
            (string) $varian['PemisahAtribut'],
            (string) $varian['PemisahNamaNilai'],
        );
    }

    /**
     * @param  array<mixed>  $peta
     * @return array<string, list<string>>
     */
    private static function KecilkanPeta(array $peta): array
    {
        $hasil = [];

        foreach ($peta as $kunci => $kata) {
            $hasil[(string) $kunci] = array_values(array_map(fn (mixed $isi): string => mb_strtolower(trim((string) $isi)), (array) $kata));
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $kunciBoleh
     * @return list<string>
     */
    private static function ValidasiPeta(mixed $peta, array $kunciBoleh, string $nama, bool $wajibLengkap): array
    {
        if (! is_array($peta)) {
            return ["{$nama} wajib objek"];
        }

        $galat = [];

        foreach ($peta as $kunci => $kata) {
            if (! in_array((string) $kunci, $kunciBoleh, true)) {
                $galat[] = "{$nama}.{$kunci} tidak dikenal";
            }

            if (! self::CekDaftarTeks($kata, false)) {
                $galat[] = "{$nama}.{$kunci} harus daftar teks";
            }
        }

        if ($wajibLengkap && array_diff($kunciBoleh, array_map('strval', array_keys($peta))) !== []) {
            $galat[] = "{$nama} wajib berisi ".implode(', ', $kunciBoleh);
        }

        return $galat;
    }

    private static function CekDaftarTeks(mixed $isi, bool $wajibIsi): bool
    {
        if (! is_array($isi) || ! array_is_list($isi) || ($wajibIsi && $isi === [])) {
            return false;
        }

        foreach ($isi as $teks) {
            if (! is_string($teks) || trim($teks) === '') {
                return false;
            }
        }

        return true;
    }
}
