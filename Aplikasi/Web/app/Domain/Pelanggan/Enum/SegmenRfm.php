<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Segmen RFM sederhana CRM-07 (recency = hari sejak belanja terakhir, frequency = transaksi 365 hari, monetary = belanja
 * 365 hari). Aturan di `PenggolongRfm`, dicek berurutan dari atas.
 */
enum SegmenRfm: string
{
    case Juara = 'Juara';
    case Setia = 'Setia';
    case Baru = 'Baru';
    case Potensial = 'Potensial';
    case Berisiko = 'Berisiko';
    case Hilang = 'Hilang';
    case BelumBelanja = 'BelumBelanja';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Juara => 'Juara',
            self::Setia => 'Setia',
            self::Baru => 'Baru',
            self::Potensial => 'Potensial',
            self::Berisiko => 'Berisiko pergi',
            self::Hilang => 'Lama tidak datang',
            self::BelumBelanja => 'Belum pernah belanja',
        };
    }

    public function AmbilKeterangan(): string
    {
        return match ($this) {
            self::Juara => 'Belanja ≤ 30 hari lalu dan sering (≥ 6 kali setahun) atau belanjanya termasuk 20% terbesar.',
            self::Setia => 'Belanja ≤ 60 hari lalu dan ≥ 3 kali setahun.',
            self::Baru => 'Baru sekali belanja, ≤ 60 hari lalu.',
            self::Potensial => 'Belanja ≤ 60 hari lalu, belum sering.',
            self::Berisiko => 'Terakhir belanja 61–180 hari lalu.',
            self::Hilang => 'Terakhir belanja lebih dari 180 hari lalu.',
            self::BelumBelanja => 'Terdaftar tetapi belum ada transaksi.',
        };
    }
}
