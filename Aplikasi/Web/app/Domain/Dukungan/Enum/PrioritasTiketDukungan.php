<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Enum;

/**
 * Prioritas tiket (P-09). Dipilih pelapor dengan bahasa dampak, bisa diubah tim Dukungan saat triase.
 */
enum PrioritasTiketDukungan: string
{
    case Mendesak = 'Mendesak';
    case Tinggi = 'Tinggi';
    case Normal = 'Normal';
    case Rendah = 'Rendah';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Mendesak => 'Mendesak',
            self::Tinggi => 'Tinggi',
            self::Normal => 'Normal',
            self::Rendah => 'Rendah',
        };
    }

    public function AmbilKeterangan(): string
    {
        return match ($this) {
            self::Mendesak => 'Usaha tidak bisa berjualan sama sekali',
            self::Tinggi => 'Sebagian fitur penting terganggu',
            self::Normal => 'Ada kendala, tetapi usaha tetap berjalan',
            self::Rendah => 'Pertanyaan atau saran',
        };
    }

    /** Urutan antrean: angka kecil didahulukan. */
    public function AmbilUrutan(): int
    {
        return match ($this) {
            self::Mendesak => 1,
            self::Tinggi => 2,
            self::Normal => 3,
            self::Rendah => 4,
        };
    }
}
