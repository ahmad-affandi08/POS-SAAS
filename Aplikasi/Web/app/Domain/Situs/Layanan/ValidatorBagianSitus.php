<?php

declare(strict_types=1);

namespace App\Domain\Situs\Layanan;

use App\Domain\Situs\Model\GambarSitus;
use Illuminate\Validation\ValidationException;

/**
 * Memeriksa & merapikan blok halaman situs menurut `SkemaBagianSitus` (D-21). Bidang di luar skema dibuang, teks
 * dipangkas, bidang kosong opsional menjadi `null`/kosong. Galat dikumpulkan dengan kunci `Bagian.{i}.{bidang}` agar
 * editor konsol bisa menandai bidangnya.
 */
final class ValidatorBagianSitus
{
    /** @var array<string, string> */
    private array $galat = [];

    /** @var array<string, true> */
    private array $gambarDipakai = [];

    /**
     * @param  mixed  $bagian  larik blok dari permintaan
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function Periksa(mixed $bagian): array
    {
        $this->galat = [];
        $this->gambarDipakai = [];

        if (! is_array($bagian) || ! array_is_list($bagian)) {
            throw ValidationException::withMessages(['Bagian' => 'Susunan blok halaman tidak valid.']);
        }

        if (count($bagian) > SkemaBagianSitus::MAKS_BAGIAN) {
            throw ValidationException::withMessages(['Bagian' => 'Satu halaman paling banyak '.SkemaBagianSitus::MAKS_BAGIAN.' blok.']);
        }

        $skema = SkemaBagianSitus::AmbilSkema();
        $hasil = [];

        foreach ($bagian as $i => $blok) {
            $jenis = is_array($blok) ? ($blok['Jenis'] ?? null) : null;

            if (! is_string($jenis) || ! isset($skema[$jenis])) {
                $this->galat["Bagian.{$i}.Jenis"] = 'Jenis blok tidak dikenal.';

                continue;
            }

            /** @var array<string, mixed> $blok */
            $hasil[] = ['Jenis' => $jenis, ...$this->PeriksaBidang($blok, $skema[$jenis], "Bagian.{$i}")];
        }

        $this->PeriksaGambarAda();

        if ($this->galat !== []) {
            throw ValidationException::withMessages($this->galat);
        }

        return $hasil;
    }

    /** Bentuk tautan yang diterima (dipakai juga pengaturan menu situs). */
    public static function CekTautanValid(string $tautan): bool
    {
        if (in_array($tautan, SkemaBagianSitus::PINTASAN_TAUTAN, true)) {
            return true;
        }

        if (preg_match('@^(/[A-Za-z0-9\-._~/%?=&]*)?(#[A-Za-z0-9\-_]*)?$@', $tautan) === 1 && $tautan !== '') {
            return ! str_starts_with($tautan, '//');
        }

        if (preg_match('~^(mailto:[^\s<>"]+|tel:\+?[0-9\-\s]{6,20})$~', $tautan) === 1) {
            return true;
        }

        return str_starts_with($tautan, 'https://') && filter_var($tautan, FILTER_VALIDATE_URL) !== false && ! preg_match('~[\s<>"]~', $tautan);
    }

    /** Id video YouTube dari URL `youtube.com/watch?v=…`, `youtu.be/…`, atau `youtube.com/embed/…`; null bila bukan. */
    public static function AmbilIdYoutube(string $url): ?string
    {
        if (preg_match('~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $cocok) === 1) {
            return $cocok[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, mixed>>  $skema
     * @return array<string, mixed>
     */
    private function PeriksaBidang(array $data, array $skema, string $awalan): array
    {
        $hasil = [];

        foreach ($skema as $bidang => $aturan) {
            $kunci = "{$awalan}.{$bidang}";
            $nilai = $data[$bidang] ?? null;
            $hasil[$bidang] = match ($aturan[0]) {
                'Teks', 'TeksPanjang' => $this->PeriksaTeks($nilai, (int) $aturan[1], (bool) ($aturan[2] ?? false), $aturan[0] === 'Teks', $kunci),
                'Tombol' => $this->PeriksaTombol($nilai, $kunci),
                'Tautan' => $this->PeriksaTautan($nilai, (bool) ($aturan[1] ?? false), $kunci, $bidang === 'UrlYoutube'),
                'Gambar' => $this->PeriksaGambar($nilai, (bool) ($aturan[1] ?? false), $kunci),
                'Ikon' => $this->PeriksaPilihan($nilai, SkemaBagianSitus::IKON, $kunci),
                'Pilihan' => $this->PeriksaPilihan($nilai, $aturan[1], $kunci),
                'Bilangan' => $this->PeriksaBilangan($nilai, (int) $aturan[1], (int) $aturan[2], $kunci),
                'Benar' => filter_var($nilai, FILTER_VALIDATE_BOOL),
                'Daftar' => $this->PeriksaDaftar($nilai, (int) $aturan[1], (int) $aturan[2], $aturan[3], $kunci),
                default => null,
            };
        }

        return $hasil;
    }

    private function PeriksaTeks(mixed $nilai, int $maks, bool $wajib, bool $satuBaris, string $kunci): ?string
    {
        $teks = is_scalar($nilai) ? trim(str_replace("\r\n", "\n", (string) $nilai)) : '';

        if ($satuBaris) {
            $teks = preg_replace('/\s+/u', ' ', $teks) ?? '';
        }

        if ($teks === '') {
            if ($wajib) {
                $this->galat[$kunci] = 'Wajib diisi.';
            }

            return null;
        }

        if (mb_strlen($teks) > $maks) {
            $this->galat[$kunci] = "Paling panjang {$maks} karakter.";
        }

        return $teks;
    }

    /**
     * @return array{Label: string, Tautan: string}|null
     */
    private function PeriksaTombol(mixed $nilai, string $kunci): ?array
    {
        $label = is_array($nilai) ? $this->PeriksaTeks($nilai['Label'] ?? null, 40, false, true, "{$kunci}.Label") : null;
        $tautan = is_array($nilai) ? $this->PeriksaTautan($nilai['Tautan'] ?? null, false, "{$kunci}.Tautan", false) : null;

        if ($label === null && $tautan === null) {
            return null;
        }

        if ($label === null) {
            $this->galat["{$kunci}.Label"] = 'Isi teks tombol atau kosongkan tautannya.';
        }

        if ($tautan === null) {
            $this->galat["{$kunci}.Tautan"] = 'Isi tautan tombol atau kosongkan teksnya.';
        }

        return ['Label' => (string) $label, 'Tautan' => (string) $tautan];
    }

    private function PeriksaTautan(mixed $nilai, bool $wajib, string $kunci, bool $youtube): ?string
    {
        $tautan = is_scalar($nilai) ? trim((string) $nilai) : '';

        if ($tautan === '') {
            if ($wajib) {
                $this->galat[$kunci] = 'Wajib diisi.';
            }

            return null;
        }

        if (mb_strlen($tautan) > 500) {
            $this->galat[$kunci] = 'Tautan terlalu panjang.';
        } elseif ($youtube ? self::AmbilIdYoutube($tautan) === null : ! self::CekTautanValid($tautan)) {
            $this->galat[$kunci] = $youtube
                ? 'Isi alamat video YouTube (https://www.youtube.com/watch?v=… atau https://youtu.be/…).'
                : 'Tautan harus diawali "/", "#", "https://", "mailto:", "tel:", atau pintasan seperti @daftar.';
        }

        return $tautan;
    }

    private function PeriksaGambar(mixed $nilai, bool $wajib, string $kunci): ?string
    {
        $uuid = is_string($nilai) ? strtoupper(trim($nilai)) : '';

        if ($uuid === '') {
            if ($wajib) {
                $this->galat[$kunci] = 'Pilih gambar.';
            }

            return null;
        }

        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $uuid) !== 1) {
            $this->galat[$kunci] = 'Gambar tidak dikenal.';

            return null;
        }

        $this->gambarDipakai[$uuid] = true;

        return $uuid;
    }

    /**
     * @param  list<string>  $pilihan
     */
    private function PeriksaPilihan(mixed $nilai, array $pilihan, string $kunci): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (! is_string($nilai) || ! in_array($nilai, $pilihan, true)) {
            $this->galat[$kunci] = 'Pilihan tidak dikenal.';

            return null;
        }

        return $nilai;
    }

    private function PeriksaBilangan(mixed $nilai, int $min, int $maks, string $kunci): ?int
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $angka = filter_var($nilai, FILTER_VALIDATE_INT);

        if ($angka === false || $angka < $min || $angka > $maks) {
            $this->galat[$kunci] = "Isi angka {$min} sampai {$maks}.";

            return null;
        }

        return $angka;
    }

    /**
     * @param  array<string, array<int, mixed>>  $subskema
     * @return list<array<string, mixed>>
     */
    private function PeriksaDaftar(mixed $nilai, int $min, int $maks, array $subskema, string $kunci): array
    {
        $daftar = is_array($nilai) ? array_values($nilai) : [];

        if (count($daftar) < $min) {
            $this->galat[$kunci] = "Tambahkan minimal {$min} item.";
        }

        if (count($daftar) > $maks) {
            $this->galat[$kunci] = "Paling banyak {$maks} item.";
            $daftar = array_slice($daftar, 0, $maks);
        }

        $hasil = [];

        foreach ($daftar as $j => $item) {
            /** @var array<string, mixed> $isi */
            $isi = is_array($item) ? $item : [];
            $hasil[] = $this->PeriksaBidang($isi, $subskema, "{$kunci}.{$j}");
        }

        return $hasil;
    }

    private function PeriksaGambarAda(): void
    {
        if ($this->gambarDipakai === []) {
            return;
        }

        $ada = GambarSitus::query()->whereIn('Uuid', array_keys($this->gambarDipakai))->pluck('Uuid')->all();
        $hilang = array_diff(array_keys($this->gambarDipakai), $ada);

        if ($hilang !== []) {
            $this->galat['Bagian'] = 'Ada gambar yang sudah dihapus dari pustaka. Pilih gambar lain.';
        }
    }
}
