<?php

declare(strict_types=1);

/*
 * D-21 Situs pemasaran (payou.id) yang diatur dari konsol. Kunci konfigurasi PascalCase (D-05).
 */
return [
    // Disk penyimpanan gambar situs (bawaan `public`; bisa S3-compatible lewat .env).
    'Disk' => env('SITUS_DISK', 'public'),

    // Batas unggahan gambar (KB) & jenis yang diterima.
    'UkuranGambarMaksKb' => 3072,
    // SVG tidak diterima (bisa memuat skrip).
    'TipeGambar' => ['image/jpeg', 'image/png', 'image/webp'],

    // Pratinjau draf halaman dari konsol: tautan bertanda tangan berlaku sekian menit.
    'MenitPratinjau' => 30,
];
