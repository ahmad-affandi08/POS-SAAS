<?php

declare(strict_types=1);

/*
 * Platform Pengelola (P-01, PRD §13.8, D-07). Kunci konfigurasi PascalCase (D-05).
 */
return [
    // Subdomain Platform Pengelola, misal pengelola.{{app}}.id. Rute tenant tidak pernah dilayani di host ini.
    'Domain' => env('PENGELOLA_DOMAIN', 'pengelola.localhost'),

    // Cookie sesi terpisah dari cookie tenant (BR-P01.4).
    'CookieSesi' => env('PENGELOLA_COOKIE_SESI', 'sesi_pengelola'),

    // BR-P01.2: sesi berakhir setelah 30 menit tidak aktif.
    'MenitSesiTidakAktif' => 30,

    // P-01 langkah 3: undangan berlaku 48 jam.
    'JamBerlakuUndangan' => 48,

    // BR-P01.1: minimal 2 Super Admin aktif.
    'MinimalSuperAdminAktif' => 2,

    // Nama yang tampil di aplikasi autentikator (Google Authenticator, Aegis, dsb.).
    'NamaPenerbit2fa' => env('APP_NAME', 'PAYOU').' Pengelola',
];
