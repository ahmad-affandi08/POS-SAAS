<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Bidang tujuan pemetaan kolom impor stok awal (DesainF05a B.4, C.7). `AmbilLabel()` = judul kolom di templat.
 * Wajib: (Sku atau Barcode atau NamaProduk) + Jumlah + HargaModal + (Lokasi atau lokasi bawaan).
 */
enum BidangImporStokAwal: string
{
    case Sku = 'Sku';
    case Barcode = 'Barcode';
    case NamaProduk = 'NamaProduk';
    case Lokasi = 'Lokasi';
    case Jumlah = 'Jumlah';
    case HargaModal = 'HargaModal';
    case NomorBatch = 'NomorBatch';
    case TanggalKedaluwarsa = 'TanggalKedaluwarsa';
    case NomorSeri = 'NomorSeri';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Sku => 'SKU',
            self::Barcode => 'Barcode',
            self::NamaProduk => 'Nama Produk',
            self::Lokasi => 'Lokasi Stok',
            self::Jumlah => 'Stok',
            self::HargaModal => 'Harga Modal',
            self::NomorBatch => 'Nomor Batch',
            self::TanggalKedaluwarsa => 'Kedaluwarsa',
            self::NomorSeri => 'Nomor Seri',
        };
    }
}
