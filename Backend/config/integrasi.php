<?php

declare(strict_types=1);

/*
 * Nilai integrasi platform (P-05). Diisi saat aplikasi berjalan oleh PenerapKonfigurasiIntegrasi dari konfigurasi
 * aktif di Platform Pengelola. Kunci PascalCase (D-05).
 */
return [
    // Cloudflare Turnstile untuk registrasi tenant (BR-00.4).
    'Turnstile' => [
        'KunciSitus' => null,
        'KunciRahasia' => null,
    ],

    // Disk `Objek` (S3-compatible) tersedia bila konfigurasi penyimpanan aktif.
    'PenyimpananObjekAktif' => false,

    // BR-P05.3: uji berkala integrasi aktif.
    'MenitUjiBerkala' => 60,
];
