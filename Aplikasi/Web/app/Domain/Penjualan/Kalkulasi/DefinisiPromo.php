<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Definisi satu promo (PRD F-16 Promo Engine, "Rincian F-16c"); padanan `DefinisiPromo` di `Paket/MesinKasir`.
 * Daftar kosong = tanpa batasan. Waktu `[mulaiPada, selesaiPada)` UTC; `hari` 1 = Senin … 7 = Minggu dan jam
 * `[jamMulai, jamSelesai)` (menit sejak 00:00) memakai jam lokal outlet; jam selesai ≤ jam mulai = lewat tengah malam.
 */
final readonly class DefinisiPromo
{
    /**
     * @param  list<int>  $hari
     * @param  list<string>  $uuidOutlet
     * @param  list<KanalPenjualan>  $kanal
     * @param  list<string>  $tier
     * @param  list<string>  $uuidKondisi
     */
    public function __construct(
        public string $uuid,
        public string $kode,
        public JenisAksiPromo $aksi,
        public int $prioritas = 0,
        public bool $eksklusif = false,
        public ?CarbonImmutable $mulaiPada = null,
        public ?CarbonImmutable $selesaiPada = null,
        public array $hari = [],
        public ?int $jamMulai = null,
        public ?int $jamSelesai = null,
        public array $uuidOutlet = [],
        public array $kanal = [],
        public array $tier = [],
        public ?Uang $minimalSubtotal = null,
        public JenisKondisiPromo $kondisi = JenisKondisiPromo::Semua,
        public array $uuidKondisi = [],
        public ?Kuantitas $jumlahMinimal = null,
        public ?BigDecimal $persen = null,
        public ?Uang $jumlah = null,
        public ?Uang $harga = null,
        public ?int $beli = null,
        public ?int $gratis = null,
        public ?BigDecimal $persenGratis = null,
        public ?int $batasPerTransaksi = null,
        public ?int $kuotaTersisa = null,
    ) {}

    /**
     * Membaca kolom promo + `Definisi` (bentuk yang sama di tabel `Promo`, katalog POS, dan test vector):
     * `{Hari, JamMulai "HH:MM", JamSelesai, Outlet, Kanal, Tier, MinimalSubtotal, Kondisi {Jenis, Uuid, JumlahMinimal},
     * Aksi {Jenis, Persen, Jumlah, Harga, Beli, Gratis, PersenGratis}, BatasPerTransaksi}`.
     *
     * @param  array<string, mixed>  $definisi
     */
    public static function Urai(
        string $uuid,
        string $kode,
        array $definisi,
        int $prioritas = 0,
        bool $eksklusif = false,
        ?CarbonImmutable $mulaiPada = null,
        ?CarbonImmutable $selesaiPada = null,
        ?int $kuotaTersisa = null,
    ): self {
        /** @var array<string, mixed> $kondisi */
        $kondisi = is_array($definisi['Kondisi'] ?? null) ? $definisi['Kondisi'] : [];
        /** @var array<string, mixed> $aksi */
        $aksi = is_array($definisi['Aksi'] ?? null) ? $definisi['Aksi'] : [];

        return new self(
            uuid: $uuid,
            kode: $kode,
            aksi: JenisAksiPromo::from((string) ($aksi['Jenis'] ?? '')),
            prioritas: $prioritas,
            eksklusif: $eksklusif,
            mulaiPada: $mulaiPada,
            selesaiPada: $selesaiPada,
            hari: array_values(array_filter(is_array($definisi['Hari'] ?? null) ? $definisi['Hari'] : [], 'is_int')),
            jamMulai: self::UraiMenit($definisi['JamMulai'] ?? null),
            jamSelesai: self::UraiMenit($definisi['JamSelesai'] ?? null),
            uuidOutlet: self::UraiDaftar($definisi['Outlet'] ?? null),
            kanal: array_map(fn (string $k): KanalPenjualan => KanalPenjualan::from($k), self::UraiDaftar($definisi['Kanal'] ?? null)),
            tier: self::UraiDaftar($definisi['Tier'] ?? null),
            minimalSubtotal: Uang::Dari(self::UraiTeks($definisi['MinimalSubtotal'] ?? null) ?? '0'),
            kondisi: JenisKondisiPromo::from(self::UraiTeks($kondisi['Jenis'] ?? null) ?? 'Semua'),
            uuidKondisi: self::UraiDaftar($kondisi['Uuid'] ?? null),
            jumlahMinimal: Kuantitas::Dari(self::UraiTeks($kondisi['JumlahMinimal'] ?? null) ?? '0'),
            persen: ($p = self::UraiTeks($aksi['Persen'] ?? null)) === null ? null : BigDecimal::of($p),
            jumlah: ($j = self::UraiTeks($aksi['Jumlah'] ?? null)) === null ? null : Uang::Dari($j),
            harga: ($h = self::UraiTeks($aksi['Harga'] ?? null)) === null ? null : Uang::Dari($h),
            beli: is_int($aksi['Beli'] ?? null) ? $aksi['Beli'] : null,
            gratis: is_int($aksi['Gratis'] ?? null) ? $aksi['Gratis'] : null,
            persenGratis: ($g = self::UraiTeks($aksi['PersenGratis'] ?? null)) === null ? null : BigDecimal::of($g),
            batasPerTransaksi: is_int($definisi['BatasPerTransaksi'] ?? null) ? $definisi['BatasPerTransaksi'] : null,
            kuotaTersisa: $kuotaTersisa,
        );
    }

    public function AmbilMinimalSubtotal(): Uang
    {
        return $this->minimalSubtotal ?? Uang::Nol();
    }

    public function AmbilJumlahMinimal(): Kuantitas
    {
        return $this->jumlahMinimal ?? Kuantitas::Nol();
    }

    public function AmbilPersenGratis(): BigDecimal
    {
        return $this->persenGratis ?? BigDecimal::of(100);
    }

    private static function UraiTeks(mixed $nilai): ?string
    {
        return is_string($nilai) && $nilai !== '' ? $nilai : null;
    }

    /**
     * @return list<string>
     */
    private static function UraiDaftar(mixed $nilai): array
    {
        return is_array($nilai) ? array_values(array_filter($nilai, 'is_string')) : [];
    }

    private static function UraiMenit(mixed $nilai): ?int
    {
        $teks = self::UraiTeks($nilai);

        if ($teks === null) {
            return null;
        }

        [$jam, $menit] = explode(':', $teks) + [1 => '0'];

        return (int) $jam * 60 + (int) $menit;
    }
}
