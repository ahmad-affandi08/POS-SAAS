<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Enum;

/**
 * Status dokumen yang **langsung diposting** saat disimpan dan hanya bisa dikoreksi lewat dokumen pembalik (aturan #8):
 * `Diposting → Dibatalkan`, dan `Dibatalkan` final.
 *
 * Rumah bersama untuk pola yang sudah ada tiga kali di repo ini: `Pembelian\StatusDokumenPembelian` (F-04),
 * `Penjualan\StatusDokumenGrosir` (F-12), dan pemakai pertama kelas ini, `Pencairan` (F-08). Catatan di
 * `JagaDokumenGrosir` sudah berjanji menyatukannya begitu domain ketiga membutuhkannya — inilah tempatnya. Kedua enum
 * lama **belum** dipindahkan ke sini: nilainya sama persis ('Diposting'/'Dibatalkan') sehingga pemindahannya murni
 * ganti nama di 44 berkas, dan itu layak jadi commit tersendiri yang diffnya tidak bercampur logika uang — bukan
 * ditumpangkan pada fitur baru. Dicatat di PRD supaya tidak terlupakan.
 */
enum StatusDokumenTerposting: string
{
    case Diposting = 'Diposting';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diposting && $tujuan === self::Dibatalkan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diposting => 'Diposting',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
