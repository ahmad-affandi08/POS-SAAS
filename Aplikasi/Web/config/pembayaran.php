<?php

declare(strict_types=1);

/*
 * Metode pembayaran tenant (F-01 langkah 5, F-08). Kunci PascalCase (D-05).
 */
return [
    // QRIS statis: gambar QR dari penerbit disimpan di disk privat, diunduh hanya lewat rute back-office.
    'DiskGambarQris' => env('PEMBAYARAN_DISK_GAMBAR_QRIS', 'local'),
    'UkuranMaksimalGambarQrisKb' => 2048,
    'EkstensiGambarQris' => ['png', 'jpg', 'jpeg', 'webp'],

    // Biaya MDR/EDC maksimal yang bisa diisi di panduan awal (persen).
    'PersenBiayaMaksimal' => '10',
];
