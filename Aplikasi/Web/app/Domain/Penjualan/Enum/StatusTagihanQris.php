<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status tagihan QRIS dinamis (F-08, BR-08.5): `Menunggu` → `Lunas` (webhook/cek status gerbang) | `Kedaluwarsa`
 * (gerbang atau 15 menit + tenggang) | `Gagal` (ditolak gerbang) | `Dibatalkan` (kasir). Uang yang ternyata diterima
 * gerbang setelah tagihan kedaluwarsa/gagal/dibatalkan tetap dicatat `Lunas` (uang nyata menang), `Lunas` final.
 *
 * Audit P0 F-02: `TidakPasti` = gerbang mungkin sudah membuat tagihan tetapi jawabannya tidak diterima (koneksi putus,
 * 5xx, respons rusak, proses terhenti). QR-nya tidak pernah tampil ke pelanggan; tagihan tidak dihapus, direkonsiliasi
 * (webhook, cek status berdasarkan `NomorPesanan`, atau kedaluwarsa setelah batas waktu).
 */
enum StatusTagihanQris: string
{
    case Menunggu = 'Menunggu';
    case Lunas = 'Lunas';
    case Kedaluwarsa = 'Kedaluwarsa';
    case Gagal = 'Gagal';
    case Dibatalkan = 'Dibatalkan';
    case TidakPasti = 'TidakPasti';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Menunggu => $tujuan !== self::Menunggu,
            self::TidakPasti => ! in_array($tujuan, [self::Menunggu, self::TidakPasti], true),
            self::Kedaluwarsa, self::Gagal, self::Dibatalkan => $tujuan === self::Lunas,
            self::Lunas => false,
        };
    }
}
