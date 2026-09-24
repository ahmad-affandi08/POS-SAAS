<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Jenis dokumen sumber sebuah `Jurnal` (`Jurnal.JenisSumber`, DesainF05a B.4). Flow berikutnya menambah case
 * (Penjualan, PenerimaanBarang, …). Tautan dibangun dari Uuid sumber tanpa kueri lintas domain.
 */
enum JenisSumberJurnal: string
{
    case StokAwal = 'StokAwal';
    case MutasiKas = 'MutasiKas';
    case Penjualan = 'Penjualan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::MutasiKas => 'Kas masuk/keluar',
            self::Penjualan => 'Penjualan',
        };
    }

    /** Tautan back-office ke dokumen sumber; null bila Uuid kosong atau halaman dokumennya belum ada. */
    public function BuatTautan(?string $uuid): ?string
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return match ($this) {
            self::StokAwal => '/kelola/persediaan/stok-awal/'.$uuid,
            self::MutasiKas => '/kelola/kasir/mutasi-kas/'.$uuid,
            self::Penjualan => '/kelola/penjualan/'.$uuid,
        };
    }
}
