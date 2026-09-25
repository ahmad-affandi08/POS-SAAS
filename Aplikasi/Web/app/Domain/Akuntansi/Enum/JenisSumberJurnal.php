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
    // F-05b: transfer (J-05.2/J-05.3, susut penutup J-05.4), stok opname & penyesuaian stok (J-05.4/J-05.5).
    case TransferStok = 'TransferStok';
    case StokOpname = 'StokOpname';
    case PenyesuaianStok = 'PenyesuaianStok';
    // F-04 fase 1: GRN (J-04.1, belanja stok J-04.3), faktur (J-04.2), pembayaran hutang (J-04.4), retur (J-04.5).
    case PenerimaanBarang = 'PenerimaanBarang';
    case FakturPembelian = 'FakturPembelian';
    case PembayaranHutang = 'PembayaranHutang';
    case ReturPembelian = 'ReturPembelian';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::MutasiKas => 'Kas masuk/keluar',
            self::Penjualan => 'Penjualan',
            self::ReturPenjualan => 'Retur penjualan',
            self::TutupShift => 'Selisih kas tutup shift',
            self::TransaksiKasBank => 'Transaksi kas & bank',
            self::TransferStok => 'Transfer stok',
            self::StokOpname => 'Stok opname',
            self::PenyesuaianStok => 'Penyesuaian stok',
            self::PenerimaanBarang => 'Penerimaan barang',
            self::FakturPembelian => 'Faktur pembelian',
            self::PembayaranHutang => 'Pembayaran hutang',
            self::ReturPembelian => 'Retur pembelian',
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
            self::TransferStok => '/kelola/persediaan/transfer/'.$uuid,
            self::StokOpname => '/kelola/persediaan/opname/'.$uuid,
            self::PenyesuaianStok => '/kelola/persediaan/penyesuaian/'.$uuid,
            self::PenerimaanBarang => '/kelola/pembelian/penerimaan/'.$uuid,
            self::FakturPembelian => '/kelola/pembelian/faktur/'.$uuid,
            self::PembayaranHutang => '/kelola/pembelian/pembayaran/'.$uuid,
            self::ReturPembelian => '/kelola/pembelian/retur/'.$uuid,
        };
    }
}
