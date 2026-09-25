<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Status stok opname (F-05b, BR-05.3). Perubahan hanya lewat `BisaBerubahKe()` dan dicatat di
 * `RiwayatStatusDokumen`: Berlangsung → Ditinjau | Dibatalkan; Ditinjau → Berlangsung (hitung ulang) | Disetujui |
 * Dibatalkan. Disetujui & Dibatalkan final. Berlangsung & Ditinjau = opname aktif (satu per lokasi & kategori).
 */
enum StatusStokOpname: string
{
    case Berlangsung = 'Berlangsung';
    case Ditinjau = 'Ditinjau';
    case Disetujui = 'Disetujui';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Berlangsung => [self::Ditinjau, self::Dibatalkan],
            self::Ditinjau => [self::Berlangsung, self::Disetujui, self::Dibatalkan],
            self::Disetujui, self::Dibatalkan => [],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Berlangsung => 'Sedang dihitung',
            self::Ditinjau => 'Menunggu tinjauan',
            self::Disetujui => 'Disetujui',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function CekAktif(): bool
    {
        return $this === self::Berlangsung || $this === self::Ditinjau;
    }
}
