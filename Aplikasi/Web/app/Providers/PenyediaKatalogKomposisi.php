<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use App\Domain\Katalog\PaketProduk\Kueri\KomponenPaketUntukPos;
use App\Domain\Katalog\Pilihan\Kueri\PilihanUntukPos;
use App\Domain\Katalog\Resep\Kueri\PemakaianProdukDiKomposisi;
use App\Domain\Katalog\Resep\Kueri\ResepUntukPos;
use App\Domain\Katalog\Resep\Layanan\HppBahanBelumTersedia;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-03 Tim 3 Modifier & Resep: kelompok pilihan, resep, paket produk.
 * Hanya tim pemiliknya yang mengubah file ini (DesainF03 G.2); isinya: pemeriksa pemakaian komposisi, bagian katalog POS Tim 3, dan ikatan PenyediaHppBahan.
 */
final class PenyediaKatalogKomposisi extends ServiceProvider
{
    public function register(): void
    {
        // BR-03.5 / H1: HPP bahan belum tersedia sampai F-05a mengikat ulang ke HPP rata-rata bergerak.
        $this->app->bindIf(PenyediaHppBahan::class, HppBahanBelumTersedia::class);

        // BR-03.2: bahan resep versi terbaru, bahan pilihan, dan komponen paket = produk dipakai.
        $this->app->tag(PemakaianProdukDiKomposisi::class, PemeriksaPemakaianProduk::TAG);

        // Bagian katalog POS Tim 3 (F-03 D.3).
        $this->app->tag([PilihanUntukPos::class, ResepUntukPos::class, KomponenPaketUntukPos::class], BagianKatalogPos::TAG);
    }

    public function boot(): void
    {
        // Tidak ada yang perlu di-boot: rute Tim 3 didaftarkan dari routes/KatalogKomposisi.php.
    }
}
