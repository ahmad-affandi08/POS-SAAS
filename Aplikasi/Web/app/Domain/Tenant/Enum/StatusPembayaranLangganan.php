<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status pembayaran langganan (P-08 langkah 3): Menunggu → Diterima / Ditolak. Keduanya final; bila ditolak, tenant
 * mengunggah bukti baru sebagai pembayaran baru.
 */
enum StatusPembayaranLangganan: string
{
    case Menunggu = 'Menunggu';
    case Diterima = 'Diterima';
    case Ditolak = 'Ditolak';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Menunggu && $tujuan !== self::Menunggu;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu verifikasi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }
}
