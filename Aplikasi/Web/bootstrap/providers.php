<?php

declare(strict_types=1);

use App\Providers\PenyediaAplikasi;
use App\Providers\PenyediaKasir;
use App\Providers\PenyediaKatalog;
use App\Providers\PenyediaKatalogHarga;
use App\Providers\PenyediaKatalogImpor;
use App\Providers\PenyediaKatalogKomposisi;
use App\Providers\PenyediaLaporan;
use App\Providers\PenyediaPelanggan;
use App\Providers\PenyediaPenjualan;
use App\Providers\PenyediaPersediaan;

return [
    PenyediaAplikasi::class,
    // F-03: satu provider per tim katalog (DesainF03 G.1).
    PenyediaKatalog::class,
    PenyediaKatalogHarga::class,
    PenyediaKatalogKomposisi::class,
    PenyediaKatalogImpor::class,
    // F-05a: stok awal & buku stok (ikatan kontrak Katalog ke Persediaan).
    PenyediaPersediaan::class,
    // F-06: shift & kas (penangan item sinkron POS).
    PenyediaKasir::class,
    // F-07b: penjualan dari POS (penangan item sinkron `Penjualan.Buat`).
    PenyediaPenjualan::class,
    // F-14a: laporan (penangan ringkasan penjualan harian).
    PenyediaLaporan::class,
    // F-16a: pelanggan (penangan item sinkron `Pelanggan.Buat`).
    PenyediaPelanggan::class,
];
