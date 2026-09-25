<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Status transfer stok (F-05b). Perubahan hanya lewat `BisaBerubahKe()` dan dicatat di `RiwayatStatusDokumen`:
 * Draf → Dikirim | Dibatalkan; Dikirim → DiterimaSebagian | Diterima; DiterimaSebagian → Diterima. `Diterima` =
 * transfer ditutup (semua barang diterima, atau sisa selisih dicatat sebagai susut). Diterima & Dibatalkan final.
 */
enum StatusTransferStok: string
{
    case Draf = 'Draf';
    case Dikirim = 'Dikirim';
    case DiterimaSebagian = 'DiterimaSebagian';
    case Diterima = 'Diterima';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::Dikirim, self::Dibatalkan],
            self::Dikirim => [self::DiterimaSebagian, self::Diterima],
            self::DiterimaSebagian => [self::Diterima],
            self::Diterima, self::Dibatalkan => [],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Dikirim => 'Dalam perjalanan',
            self::DiterimaSebagian => 'Diterima sebagian',
            self::Diterima => 'Diterima',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Barang sedang di lokasi dalam perjalanan: bisa diterima atau ditutup. */
    public function CekDalamPerjalanan(): bool
    {
        return $this === self::Dikirim || $this === self::DiterimaSebagian;
    }
}
