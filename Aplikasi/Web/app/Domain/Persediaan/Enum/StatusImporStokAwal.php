<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Status impor stok awal (DesainF05a B.4, C.7): nilai dan perpindahan sama dengan `StatusImporProduk` agar komponen
 * FE `LangkahImpor` bisa dipakai ulang. Diunggah → MenungguPemetaan | Gagal; MenungguPemetaan → Memvalidasi |
 * Dibatalkan; Memvalidasi → Pratinjau | Gagal; Pratinjau → MenungguPemetaan | Menerapkan | Dibatalkan;
 * Menerapkan → Selesai | Gagal; Gagal → Memvalidasi | Menerapkan (lanjutkan).
 */
enum StatusImporStokAwal: string
{
    case Diunggah = 'Diunggah';
    case MenungguPemetaan = 'MenungguPemetaan';
    case Memvalidasi = 'Memvalidasi';
    case Pratinjau = 'Pratinjau';
    case Menerapkan = 'Menerapkan';
    case Selesai = 'Selesai';
    case Gagal = 'Gagal';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Diunggah => [self::MenungguPemetaan, self::Gagal],
            self::MenungguPemetaan => [self::Memvalidasi, self::Dibatalkan],
            self::Memvalidasi => [self::Pratinjau, self::Gagal],
            self::Pratinjau => [self::MenungguPemetaan, self::Menerapkan, self::Dibatalkan],
            self::Menerapkan => [self::Selesai, self::Gagal],
            self::Gagal => [self::Memvalidasi, self::Menerapkan],
            self::Selesai, self::Dibatalkan => [],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diunggah => 'Diunggah',
            self::MenungguPemetaan => 'Menunggu pemetaan kolom',
            self::Memvalidasi => 'Memeriksa data',
            self::Pratinjau => 'Siap diimpor',
            self::Menerapkan => 'Sedang membuat draf',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Status yang masih diproses antrean (tidak ikut dipangkas retensi). */
    public function CekBerjalan(): bool
    {
        return $this === self::Memvalidasi || $this === self::Menerapkan;
    }

    /** Impor masih aktif: berkas yang sama tidak diunggah ulang sebagai impor baru (idempoten per `HashBerkas`). */
    public function CekAktif(): bool
    {
        return ! in_array($this, [self::Selesai, self::Gagal, self::Dibatalkan], true);
    }
}
