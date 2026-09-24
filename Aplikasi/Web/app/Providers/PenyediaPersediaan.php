<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Katalog\Kontrak\PemeriksaRiwayatStok;
use App\Domain\Persediaan\Kueri\RiwayatStokProduk;
use Illuminate\Support\ServiceProvider;

/**
 * Provider F-05a stok awal & buku stok (DesainF05a G, pemilik Tim 0; perubahan lewat permintaan ke lead).
 * Mengikat kontrak Katalog ke implementasi domain Persediaan.
 */
final class PenyediaPersediaan extends ServiceProvider
{
    public function register(): void
    {
        // C.4: Katalog mengunci `Pelacakan` begitu produk punya riwayat stok (implementasi Tim D).
        $this->app->bind(PemeriksaRiwayatStok::class, RiwayatStokProduk::class);

        // TODO(F-05a Tim F): daftarkan dua baris berikut bersama implementasinya (DesainF05a C.8). Sengaja belum
        // didaftarkan selama `HppBahanDariSaldo` dan `PemakaianProdukDiPersediaan` masih stub, karena keduanya
        // dipanggil fitur F-03 yang sudah berjalan (estimasi HPP resep, hapus/ubah jenis produk):
        //   $this->app->bind(PenyediaHppBahan::class, HppBahanDariSaldo::class);  // `bind` menimpa `bindIf` Katalog
        //   $this->app->tag(PemakaianProdukDiPersediaan::class, PemeriksaPemakaianProduk::TAG);
    }

    public function boot(): void
    {
        // Tidak ada yang perlu di-boot: rute didaftarkan dari routes/Persediaan*.php dan routes/Akuntansi.php.
    }
}
