<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/**
 * Status pesanan pembelian (F-04, PRD "Rincian F-04 fase 1"). Perubahan hanya lewat `BisaBerubahKe()` dan dicatat di
 * `RiwayatStatusDokumen`: Draf → MenungguPersetujuan | Disetujui | Dibatalkan; MenungguPersetujuan → Disetujui | Draf
 * (ditolak) | Dibatalkan; Disetujui → DiterimaSebagian | Diterima | Ditutup | Dibatalkan; DiterimaSebagian →
 * Diterima | Ditutup | Disetujui (penerimaan dibatalkan semua); Diterima → Ditutup | DiterimaSebagian | Disetujui
 * (penerimaan dibatalkan). Dibatalkan hanya bila belum ada penerimaan. Ditutup & Dibatalkan final.
 */
enum StatusPesananPembelian: string
{
    case Draf = 'Draf';
    case MenungguPersetujuan = 'MenungguPersetujuan';
    case Disetujui = 'Disetujui';
    case DiterimaSebagian = 'DiterimaSebagian';
    case Diterima = 'Diterima';
    case Ditutup = 'Ditutup';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::MenungguPersetujuan, self::Disetujui, self::Dibatalkan],
            self::MenungguPersetujuan => [self::Disetujui, self::Draf, self::Dibatalkan],
            self::Disetujui => [self::DiterimaSebagian, self::Diterima, self::Ditutup, self::Dibatalkan],
            self::DiterimaSebagian => [self::Diterima, self::Ditutup, self::Disetujui],
            self::Diterima => [self::Ditutup, self::DiterimaSebagian, self::Disetujui],
            self::Ditutup, self::Dibatalkan => [],
        }, true);
    }

    /** PO boleh menerima barang (GRN). */
    public function CekBolehDiterima(): bool
    {
        return $this === self::Disetujui || $this === self::DiterimaSebagian;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::MenungguPersetujuan => 'Menunggu persetujuan',
            self::Disetujui => 'Disetujui',
            self::DiterimaSebagian => 'Diterima sebagian',
            self::Diterima => 'Diterima',
            self::Ditutup => 'Ditutup',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
