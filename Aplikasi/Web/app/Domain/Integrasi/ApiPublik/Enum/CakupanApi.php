<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Enum;

/** X7 cakupan (scope) token API publik v1 (PRD §16.1). Bagian 1 hanya cakupan baca. */
enum CakupanApi: string
{
    case ProdukBaca = 'produk:baca';
    case StokBaca = 'stok:baca';
    case PenjualanBaca = 'penjualan:baca';
    case PelangganBaca = 'pelanggan:baca';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::ProdukBaca => 'Baca produk, satuan, barcode & harga dasar',
            self::StokBaca => 'Baca saldo stok per lokasi',
            self::PenjualanBaca => 'Baca penjualan beserta baris & pembayaran',
            self::PelangganBaca => 'Baca data pelanggan (nama, nomor HP, email)',
        };
    }

    /** @return list<string> */
    public static function AmbilSemuaNilai(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
