<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tabel\Data;

/**
 * Keadaan `TabelData` dari parameter URL (PRD §17.4.3, D-16): `cari`, `urut` (`Kolom`/`-Kolom`, dipisah koma),
 * `halaman`, `perHalaman` (25/50/100), `saring[Kolom]=nilai`. Kolom urut & saring disaring daftar putih milik
 * halaman; nilai tak dikenal dibuang, jadi aman diteruskan ke kueri.
 */
final readonly class DataPermintaanTabel
{
    public const UKURAN_HALAMAN = [25, 50, 100];

    /**
     * @param  list<array{Kolom: string, Turun: bool}>  $urut
     * @param  array<string, string>  $saring
     */
    public function __construct(
        public string $cari,
        public array $urut,
        public int $halaman,
        public int $perHalaman,
        public array $saring,
    ) {}

    /**
     * @param  array<mixed>  $query  parameter query mentah (`$request->query()`)
     * @param  list<string>  $kolomUrut  kolom yang boleh diurut
     * @param  string  $urutBawaan  mis. `-DibuatPada`
     * @param  list<string>  $kolomSaring  kunci `saring[...]` yang dikenal
     */
    public static function Dari(array $query, array $kolomUrut, string $urutBawaan, array $kolomSaring = []): self
    {
        $cari = is_string($query['cari'] ?? null) ? mb_substr(trim($query['cari']), 0, 100) : '';

        $urut = self::BacaUrut(is_string($query['urut'] ?? null) ? $query['urut'] : '', $kolomUrut);
        if ($urut === []) {
            $urut = self::BacaUrut($urutBawaan, $kolomUrut);
        }

        $perHalaman = filter_var($query['perHalaman'] ?? null, FILTER_VALIDATE_INT);
        $perHalaman = in_array($perHalaman, self::UKURAN_HALAMAN, true) ? $perHalaman : self::UKURAN_HALAMAN[0];
        $halaman = filter_var($query['halaman'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $saring = [];
        $mentah = is_array($query['saring'] ?? null) ? $query['saring'] : [];
        foreach ($kolomSaring as $kolom) {
            $nilai = $mentah[$kolom] ?? null;
            if (is_string($nilai) && trim($nilai) !== '') {
                $saring[$kolom] = mb_substr(trim($nilai), 0, 500);
            }
        }

        return new self($cari, $urut, is_int($halaman) ? $halaman : 1, $perHalaman, $saring);
    }

    /**
     * Nilai saring pilihan-banyak (`A,B`) sebagai daftar, dibatasi ke `$boleh` bila diberikan.
     *
     * @param  list<string>|null  $boleh
     * @return list<string>
     */
    public function AmbilDaftar(string $kolom, ?array $boleh = null): array
    {
        if (! isset($this->saring[$kolom])) {
            return [];
        }

        $nilai = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->saring[$kolom])), fn (string $n): bool => $n !== '')));

        return $boleh === null ? $nilai : array_values(array_intersect($nilai, $boleh));
    }

    /**
     * Saring rentang tanggal `YYYY-MM-DD..YYYY-MM-DD` (salah satu sisi boleh kosong). Tanggal tidak sah = null.
     *
     * @return array{Dari: string|null, Sampai: string|null}
     */
    public function AmbilRentangTanggal(string $kolom): array
    {
        [$dari, $sampai] = array_pad(explode('..', $this->saring[$kolom] ?? '', 2), 2, '');

        return ['Dari' => self::TanggalSah($dari), 'Sampai' => self::TanggalSah($sampai)];
    }

    /** Saring ya/tidak (`1`/`0`); tidak diisi = null. */
    public function AmbilBoolean(string $kolom): ?bool
    {
        return match ($this->saring[$kolom] ?? null) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * @param  list<string>  $boleh
     * @return list<array{Kolom: string, Turun: bool}>
     */
    private static function BacaUrut(string $nilai, array $boleh): array
    {
        $hasil = [];
        foreach (array_slice(explode(',', $nilai), 0, 3) as $bagian) {
            $bagian = trim($bagian);
            $turun = str_starts_with($bagian, '-');
            $kolom = ltrim($bagian, '-');
            if (in_array($kolom, $boleh, true) && ! in_array($kolom, array_column($hasil, 'Kolom'), true)) {
                $hasil[] = ['Kolom' => $kolom, 'Turun' => $turun];
            }
        }

        return $hasil;
    }

    private static function TanggalSah(string $nilai): ?string
    {
        $nilai = trim($nilai);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1 && checkdate((int) substr($nilai, 5, 2), (int) substr($nilai, 8, 2), (int) substr($nilai, 0, 4))
            ? $nilai
            : null;
    }
}
