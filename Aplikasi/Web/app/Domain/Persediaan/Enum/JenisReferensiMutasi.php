<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Jenis dokumen sumber sebuah `MutasiStok` (`MutasiStok.JenisReferensi`, DesainF05a B.4). Tautan ke dokumen sumber
 * dibangun dari Uuid tanpa kueri lintas domain; hanya dokumen yang halamannya sudah ada yang punya tautan.
 */
enum JenisReferensiMutasi: string
{
    case StokAwal = 'StokAwal';
    case PenerimaanBarang = 'PenerimaanBarang';
    case ReturPembelian = 'ReturPembelian';
    case Penjualan = 'Penjualan';
    case ReturPenjualan = 'ReturPenjualan';
    case VoidPenjualan = 'VoidPenjualan';
    case TransferStok = 'TransferStok';
    case StokOpname = 'StokOpname';
    case PenyesuaianStok = 'PenyesuaianStok';
    case Produksi = 'Produksi';
    case BahanTerbuang = 'BahanTerbuang';
    case Konsinyasi = 'Konsinyasi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::PenerimaanBarang => 'Penerimaan barang',
            self::ReturPembelian => 'Retur pembelian',
            self::Penjualan => 'Penjualan',
            self::ReturPenjualan => 'Retur penjualan',
            self::VoidPenjualan => 'Void penjualan',
            self::TransferStok => 'Transfer stok',
            self::StokOpname => 'Stok opname',
            self::PenyesuaianStok => 'Penyesuaian stok',
            self::Produksi => 'Produksi',
            self::BahanTerbuang => 'Bahan terbuang',
            self::Konsinyasi => 'Konsinyasi',
        };
    }

    /** Tautan back-office ke dokumen sumber; null bila halaman dokumennya belum ada atau Uuid kosong. */
    public function BuatTautan(?string $uuid): ?string
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return match ($this) {
            self::StokAwal => '/kelola/persediaan/stok-awal/'.$uuid,
            self::Penjualan => '/kelola/penjualan/'.$uuid,
            default => null,
        };
    }
}
