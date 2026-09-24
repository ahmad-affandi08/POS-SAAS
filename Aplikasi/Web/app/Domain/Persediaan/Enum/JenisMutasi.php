<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Jenis mutasi stok (PRD §8 F-05, BR-05.1, DesainF05a B.4). Arah menentukan tanda `MutasiStok.Jumlah`:
 * masuk = positif, keluar = negatif. `StokAwal` boleh dua arah (pembatalan stok awal = baris negatif). Baris pembalik
 * (`IdMutasiAsal` terisi) memakai jenis yang sama dengan baris asalnya dan tanda kebalikannya.
 */
enum JenisMutasi: string
{
    case StokAwal = 'StokAwal';
    case PenerimaanPembelian = 'PenerimaanPembelian';
    case ReturPembelian = 'ReturPembelian';
    case Penjualan = 'Penjualan';
    case ReturPenjualan = 'ReturPenjualan';
    case TransferKeluar = 'TransferKeluar';
    case TransferMasuk = 'TransferMasuk';
    case PenyesuaianMasuk = 'PenyesuaianMasuk';
    case PenyesuaianKeluar = 'PenyesuaianKeluar';
    case OpnameLebih = 'OpnameLebih';
    case OpnameKurang = 'OpnameKurang';
    case ProduksiPakai = 'ProduksiPakai';
    case ProduksiHasil = 'ProduksiHasil';
    case Susut = 'Susut';
    case KonsinyasiMasuk = 'KonsinyasiMasuk';
    case KonsinyasiRetur = 'KonsinyasiRetur';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::PenerimaanPembelian => 'Penerimaan pembelian',
            self::ReturPembelian => 'Retur pembelian',
            self::Penjualan => 'Penjualan',
            self::ReturPenjualan => 'Retur penjualan',
            self::TransferKeluar => 'Transfer keluar',
            self::TransferMasuk => 'Transfer masuk',
            self::PenyesuaianMasuk => 'Penyesuaian masuk',
            self::PenyesuaianKeluar => 'Penyesuaian keluar',
            self::OpnameLebih => 'Opname lebih',
            self::OpnameKurang => 'Opname kurang',
            self::ProduksiPakai => 'Bahan produksi',
            self::ProduksiHasil => 'Hasil produksi',
            self::Susut => 'Susut/terbuang',
            self::KonsinyasiMasuk => 'Konsinyasi masuk',
            self::KonsinyasiRetur => 'Retur konsinyasi',
        };
    }

    /** Boleh menambah stok (jumlah positif) sebagai baris biasa, bukan pembalik. */
    public function CekBolehMasuk(): bool
    {
        return in_array($this, [
            self::StokAwal,
            self::PenerimaanPembelian,
            self::ReturPenjualan,
            self::TransferMasuk,
            self::PenyesuaianMasuk,
            self::OpnameLebih,
            self::ProduksiHasil,
            self::KonsinyasiMasuk,
        ], true);
    }

    /** Boleh mengurangi stok (jumlah negatif) sebagai baris biasa, bukan pembalik. */
    public function CekBolehKeluar(): bool
    {
        return in_array($this, [
            self::StokAwal,
            self::ReturPembelian,
            self::Penjualan,
            self::TransferKeluar,
            self::PenyesuaianKeluar,
            self::OpnameKurang,
            self::ProduksiPakai,
            self::Susut,
            self::KonsinyasiRetur,
        ], true);
    }
}
