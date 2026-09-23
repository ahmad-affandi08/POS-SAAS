<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Kueri\KategoriSatuanUntukPos;
use App\Domain\Katalog\Kueri\ProdukUntukPos;
use App\Domain\Katalog\Layanan\PemakaianVarianProduk;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 1 Katalog Inti: produk, kategori, satuan, varian, barcode, gambar, batas stok.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: pemeriksa pemakaian varian dan bagian katalog POS Tim 1.
 */
final class PenyediaKatalog extends ServiceProvider
{
    public function register(): void
    {
        // BR-03.2: induk varian yang masih punya anak dianggap dipakai.
        $this->app->tag([PemakaianVarianProduk::class], PemeriksaPemakaianProduk::TAG);

        // D.3: bagian Kategori, Satuan, Produk, ProdukSatuan, ProdukBarcode katalog POS.
        $this->app->tag([KategoriSatuanUntukPos::class, ProdukUntukPos::class], BagianKatalogPos::TAG);
    }

    public function boot(): void
    {
        // Tidak ada yang perlu di-boot.
    }
}
