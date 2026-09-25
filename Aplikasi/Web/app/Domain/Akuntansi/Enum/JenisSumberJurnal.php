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
    // F-09 J-09.2 (void memakai jurnal pembalik ber-sumber `Penjualan`, kunci `Void`).
    case ReturPenjualan = 'ReturPenjualan';
    case TutupShift = 'TutupShift';
    // F-13a: transaksi kas & bank back-office (pengeluaran, penerimaan, transfer, dan pembaliknya).
    case TransaksiKasBank = 'TransaksiKasBank';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::MutasiKas => 'Kas masuk/keluar',
            self::Penjualan => 'Penjualan',
            self::ReturPenjualan => 'Retur penjualan',
            self::TutupShift => 'Selisih kas tutup shift',
            self::TransaksiKasBank => 'Transaksi kas & bank',
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
            self::ReturPenjualan => '/kelola/penjualan/retur/'.$uuid,
            self::TutupShift => '/kelola/kasir/shift/'.$uuid,
            self::TransaksiKasBank => '/kelola/akuntansi/kas-bank/'.$uuid,
        };
    }
}
