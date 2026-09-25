<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status dokumen penjualan (PRD F-07 diagram status). Fase 1 (F-07b) server hanya menerima penjualan `Lunas`;
 * draf/ditahan tetap lokal di perangkat. F-09 fase 1 lewat dokumen pembalik: `Void` (seluruh transaksi dibatalkan di
 * shift yang sama), `DireturSebagian` (sebagian barang diretur, sisanya masih bisa diretur), `Diretur` (semua baris
 * habis diretur). Penjualan yang sudah diretur (sebagian/penuh) tidak bisa di-void.
 */
enum StatusPenjualan: string
{
    case Lunas = 'Lunas';
    case Void = 'Void';
    case DireturSebagian = 'DireturSebagian';
    case Diretur = 'Diretur';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Lunas => 'Lunas',
            self::Void => 'Dibatalkan (void)',
            self::DireturSebagian => 'Diretur sebagian',
            self::Diretur => 'Diretur penuh',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Lunas => in_array($tujuan, [self::Void, self::DireturSebagian, self::Diretur], true),
            self::DireturSebagian => $tujuan === self::Diretur,
            self::Void, self::Diretur => false,
        };
    }

    /** Penjualan yang masih bisa diretur (BR-09.1: lunas atau baru diretur sebagian). */
    public function CekBisaDiretur(): bool
    {
        return $this === self::Lunas || $this === self::DireturSebagian;
    }
}
