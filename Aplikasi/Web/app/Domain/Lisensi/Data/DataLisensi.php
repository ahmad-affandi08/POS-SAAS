<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Data;

use App\Domain\Lisensi\Galat\LisensiTidakSah;

/**
 * Isi berkas lisensi yang ditandatangani PAYOU (D-35). Lisensi berlaku selamanya (sekali beli), untuk satu usaha di
 * satu domain, dengan semua fitur. Yang dibatasi hanya jumlah outlet, perangkat per outlet, dan pengguna (null = tak
 * terbatas).
 *
 * Urutan kunci `KeArray()` adalah bentuk kanonik yang ditandatangani: jangan diubah urutannya (lisensi lama tidak
 * akan lolos verifikasi). Kolom baru hanya boleh ditambah di akhir dengan versi format baru.
 */
final readonly class DataLisensi
{
    public const VERSI_FORMAT = 1;

    public function __construct(
        public string $nomor,
        public string $namaPemegang,
        public string $domain,
        public ?int $batasOutlet,
        public ?int $batasPerangkatPerOutlet,
        public ?int $batasPengguna,
        public string $diterbitkanPada,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function DariArray(array $data): self
    {
        $teks = static function (string $kunci) use ($data): string {
            $nilai = $data[$kunci] ?? null;

            if (! is_string($nilai) || trim($nilai) === '') {
                throw new LisensiTidakSah("Berkas lisensi rusak: {$kunci} kosong.");
            }

            return trim($nilai);
        };
        $batas = static function (string $kunci) use ($data): ?int {
            $nilai = $data[$kunci] ?? null;

            if ($nilai !== null && (! is_int($nilai) || $nilai < 1)) {
                throw new LisensiTidakSah("Berkas lisensi rusak: {$kunci} harus bilangan bulat ≥ 1 atau kosong.");
            }

            return $nilai;
        };

        $domain = strtolower($teks('Domain'));

        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $domain) !== 1) {
            throw new LisensiTidakSah('Berkas lisensi rusak: Domain bukan nama host yang sah.');
        }

        $tanggal = $teks('DiterbitkanPada');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) !== 1) {
            throw new LisensiTidakSah('Berkas lisensi rusak: DiterbitkanPada harus berformat YYYY-MM-DD.');
        }

        return new self(
            nomor: $teks('Nomor'),
            namaPemegang: $teks('NamaPemegang'),
            domain: $domain,
            batasOutlet: $batas('BatasOutlet'),
            batasPerangkatPerOutlet: $batas('BatasPerangkatPerOutlet'),
            batasPengguna: $batas('BatasPengguna'),
            diterbitkanPada: $tanggal,
        );
    }

    /**
     * @return array{Nomor: string, NamaPemegang: string, Domain: string, BatasOutlet: int|null, BatasPerangkatPerOutlet: int|null, BatasPengguna: int|null, DiterbitkanPada: string}
     */
    public function KeArray(): array
    {
        return [
            'Nomor' => $this->nomor,
            'NamaPemegang' => $this->namaPemegang,
            'Domain' => $this->domain,
            'BatasOutlet' => $this->batasOutlet,
            'BatasPerangkatPerOutlet' => $this->batasPerangkatPerOutlet,
            'BatasPengguna' => $this->batasPengguna,
            'DiterbitkanPada' => $this->diterbitkanPada,
        ];
    }

    /** Host permintaan cocok dengan domain lisensi (tanpa memperhatikan huruf besar & port). */
    public function CekDomainCocok(string $host): bool
    {
        return strtolower($host) === $this->domain;
    }
}
