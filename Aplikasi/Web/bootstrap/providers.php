<?php

declare(strict_types=1);

use App\Providers\PenyediaAplikasi;
use App\Providers\PenyediaKatalog;
use App\Providers\PenyediaKatalogHarga;
use App\Providers\PenyediaKatalogImpor;
use App\Providers\PenyediaKatalogKomposisi;
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
];
