<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status pesanan tamu dari QR meja (F-17 Self-Order, X12): `MenungguKonfirmasi` → `Diterima` (staf menerima di POS,
 * perangkat membuat pesanan terbuka) | `Ditolak` (beralasan) | `Kedaluwarsa` (tidak diproses 30 menit). Pembayaran
 * masih di kasir; status `Dibayar` (QRIS dinamis sebelum konfirmasi) akan ditambahkan tanpa mengubah status lain.
 */
enum StatusPesananSendiri: string
{
    case MenungguKonfirmasi = 'MenungguKonfirmasi';
    case Diterima = 'Diterima';
    case Ditolak = 'Ditolak';
    case Kedaluwarsa = 'Kedaluwarsa';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::MenungguKonfirmasi => 'menunggu konfirmasi',
            self::Diterima => 'diterima',
            self::Ditolak => 'ditolak',
            self::Kedaluwarsa => 'kedaluwarsa',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::MenungguKonfirmasi && $tujuan !== self::MenungguKonfirmasi;
    }
}
