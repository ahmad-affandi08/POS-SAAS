<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Enum;

/**
 * PRD §16.4: peristiwa webhook keluar yang tersedia. X7 bagian 2: penjualan selesai/void/retur (dari peristiwa domain
 * Penjualan). v3.91: produk, pelanggan, shift, penyesuaian stok, PO disetujui, penerimaan barang (dari
 * `PeristiwaIntegrasi`). `pembayaran.diterima` & `stok.menipis` belum.
 */
enum PeristiwaWebhook: string
{
    case PenjualanSelesai = 'penjualan.selesai';
    case PenjualanDivoid = 'penjualan.divoid';
    case PenjualanDiretur = 'penjualan.diretur';
    case ProdukDiubah = 'produk.diubah';
    case PelangganDibuat = 'pelanggan.dibuat';
    case ShiftDitutup = 'shift.ditutup';
    case StokDisesuaikan = 'stok.disesuaikan';
    case PesananPembelianDisetujui = 'pesanan-pembelian.disetujui';
    case PenerimaanBarangDiposting = 'penerimaan-barang.diposting';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PenjualanSelesai => 'Penjualan selesai (diterima server)',
            self::PenjualanDivoid => 'Penjualan di-void',
            self::PenjualanDiretur => 'Retur penjualan diterima',
            self::ProdukDiubah => 'Produk dibuat atau diubah',
            self::PelangganDibuat => 'Pelanggan baru dibuat',
            self::ShiftDitutup => 'Shift kasir ditutup',
            self::StokDisesuaikan => 'Penyesuaian stok diposting',
            self::PesananPembelianDisetujui => 'Pesanan pembelian disetujui',
            self::PenerimaanBarangDiposting => 'Penerimaan barang diposting',
        };
    }

    /** @return list<string> */
    public static function AmbilSemuaNilai(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
