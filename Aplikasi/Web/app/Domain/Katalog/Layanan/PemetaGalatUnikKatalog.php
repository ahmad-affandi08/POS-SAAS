<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Pengaman terakhir indeks unik katalog (DesainF03 C.0): pelanggaran indeks unik yang lolos dari pemeriksaan Aksi
 * (misal dua permintaan bersamaan) dipetakan per nama indeks ke `PelanggaranAturanBisnis`. Dipanggil di luar
 * `DB::transaction`, setelah transaksi dibatalkan seluruhnya.
 */
final class PemetaGalatUnikKatalog
{
    public static function Petakan(UniqueConstraintViolationException $galat, string $bidangBarcode = 'Barcode'): PelanggaranAturanBisnis
    {
        $pesan = strtolower($galat->getMessage());

        return match (true) {
            str_contains($pesan, 'uniqprodukidtenantsku') => new PelanggaranAturanBisnis('BR-03.1', 'SKU ini sudah dipakai produk lain. Pakai SKU lain atau kosongkan agar dibuat otomatis.', 'Sku'),
            str_contains($pesan, 'uniqprodukbarcodeidtenantbarcode') => new PelanggaranAturanBisnis('BR-03.1', 'Barcode ini sudah dipakai produk lain.', $bidangBarcode),
            str_contains($pesan, 'uniqproduksatuanidtenantidprodukidsatuan') => new PelanggaranAturanBisnis('SatuanProdukGanda', 'Satuan yang sama dipilih lebih dari sekali.', 'Satuan'),
            str_contains($pesan, 'uniqprodukidtenantidindukkuncivarian') => new PelanggaranAturanBisnis('AtributVarianDipakai', 'Kombinasi varian ini sudah ada.', 'AtributVarian'),
            default => new PelanggaranAturanBisnis('BR-03.1', 'Data yang sama baru saja disimpan. Muat ulang halaman lalu periksa lagi.'),
        };
    }
}
