<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Enum;

/**
 * Status impor produk (F-03 BR-03.6, DesainF03 C.6). Perubahan hanya lewat `BisaBerubahKe()`:
 * Diunggah → MenungguPemetaan | Gagal; MenungguPemetaan → Memvalidasi | Dibatalkan; Memvalidasi → Pratinjau | Gagal;
 * Pratinjau → MenungguPemetaan (ubah pemetaan) | Menerapkan | Dibatalkan; Menerapkan → Selesai | Gagal;
 * Gagal → Memvalidasi | Menerapkan (lanjutkan).
 */
enum StatusImporProduk: string
{
    case Diunggah = 'Diunggah';
    case MenungguPemetaan = 'MenungguPemetaan';
    case Memvalidasi = 'Memvalidasi';
    case Pratinjau = 'Pratinjau';
    case Menerapkan = 'Menerapkan';
    case Selesai = 'Selesai';
    case Gagal = 'Gagal';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Diunggah => [self::MenungguPemetaan, self::Gagal],
            self::MenungguPemetaan => [self::Memvalidasi, self::Dibatalkan],
            self::Memvalidasi => [self::Pratinjau, self::Gagal],
            self::Pratinjau => [self::MenungguPemetaan, self::Menerapkan, self::Dibatalkan],
            self::Menerapkan => [self::Selesai, self::Gagal],
            self::Gagal => [self::Memvalidasi, self::Menerapkan],
            self::Selesai, self::Dibatalkan => [],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diunggah => 'Diunggah',
            self::MenungguPemetaan => 'Menunggu pemetaan kolom',
            self::Memvalidasi => 'Memeriksa data',
            self::Pratinjau => 'Siap diimpor',
            self::Menerapkan => 'Sedang diimpor',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Status yang masih diproses antrean (tidak ikut dipangkas retensi). */
    public function CekBerjalan(): bool
    {
        return $this === self::Memvalidasi || $this === self::Menerapkan;
    }
}
