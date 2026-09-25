<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status pesanan penjualan / pre-order (F-12 bagian 2, F-10 "Pre-order & pesanan kustom"): `Dipesan` → `Siap` →
 * `Diambil` (lewat `Penjualan.Buat` yang merujuknya), atau `Dibatalkan` sebelum diambil. Void penjualan pengambilan
 * mengembalikan pesanan ke `Siap` agar bisa diambil ulang.
 */
enum StatusPesananPenjualan: string
{
    case Dipesan = 'Dipesan';
    case Siap = 'Siap';
    case Diambil = 'Diambil';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Dipesan => 'Dipesan',
            self::Siap => 'Siap diambil',
            self::Diambil => 'Diambil',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Pesanan yang masih menunggu pengambilan. */
    public function CekTerbuka(): bool
    {
        return $this === self::Dipesan || $this === self::Siap;
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Dipesan => in_array($tujuan, [self::Siap, self::Diambil, self::Dibatalkan], true),
            self::Siap => in_array($tujuan, [self::Diambil, self::Dibatalkan], true),
            self::Diambil => $tujuan === self::Siap,
            self::Dibatalkan => false,
        };
    }
}
