<?php

declare(strict_types=1);

/*
 * Versi aplikasi POS per platform untuk `GET /api/pos/v1/konfigurasi-aplikasi` (PRD §14.6, F-02b). Kunci PascalCase
 * (D-05). Versi semantik `MAJOR.MINOR.PATCH` (bagian `+BUILD` diabaikan saat dibandingkan). Aplikasi di bawah
 * `VersiMinimal` tetap boleh mengirim outbox tertunda lalu mengunci layar jual sampai diperbarui.
 *
 * TODO P-10: pindah ke tabel `RilisAplikasi` (kanal Beta/Stabil, rilis bertahap) dan flag fitur remote.
 */
return [
    'Pos' => [
        'Android' => [
            'VersiTerbaru' => env('APLIKASI_POS_ANDROID_VERSI_TERBARU', '1.0.0'),
            'VersiMinimal' => env('APLIKASI_POS_ANDROID_VERSI_MINIMAL', '1.0.0'),
            'TautanUnduh' => env('APLIKASI_POS_ANDROID_TAUTAN_UNDUH'),
        ],
        'Ios' => [
            'VersiTerbaru' => env('APLIKASI_POS_IOS_VERSI_TERBARU', '1.0.0'),
            'VersiMinimal' => env('APLIKASI_POS_IOS_VERSI_MINIMAL', '1.0.0'),
            'TautanUnduh' => env('APLIKASI_POS_IOS_TAUTAN_UNDUH'),
        ],
        'Windows' => [
            'VersiTerbaru' => env('APLIKASI_POS_WINDOWS_VERSI_TERBARU', '1.0.0'),
            'VersiMinimal' => env('APLIKASI_POS_WINDOWS_VERSI_MINIMAL', '1.0.0'),
            'TautanUnduh' => env('APLIKASI_POS_WINDOWS_TAUTAN_UNDUH'),
        ],
    ],
];
