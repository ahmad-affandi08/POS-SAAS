<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Jenis produk (PRD §15.3 `Produk.Jenis`, F-03). F-01 hanya membuat Stok, NonStok, dan Jasa (produk awal).
 */
enum JenisProduk: string
{
    case Stok = 'Stok';
    case IndukVarian = 'IndukVarian';
    case Resep = 'Resep';
    case Produksi = 'Produksi';
    case Paket = 'Paket';
    case Jasa = 'Jasa';
    case NonStok = 'NonStok';
    case BahanBaku = 'BahanBaku';
    case Konsinyasi = 'Konsinyasi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Stok => 'Barang stok',
            self::IndukVarian => 'Induk varian',
            self::Resep => 'Resep',
            self::Produksi => 'Produksi',
            self::Paket => 'Paket/bundel',
            self::Jasa => 'Jasa',
            self::NonStok => 'Tanpa stok',
            self::BahanBaku => 'Bahan baku',
            self::Konsinyasi => 'Konsinyasi',
        };
    }

    /** Jenis yang boleh dipakai produk contoh template sektor (P-03) dan produk cepat (F-01). */
    public function CekBolehProdukAwal(): bool
    {
        return in_array($this, [self::Stok, self::NonStok, self::Jasa], true);
    }
}
