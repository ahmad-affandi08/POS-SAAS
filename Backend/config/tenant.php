<?php

declare(strict_types=1);

/*
 * Pendaftaran tenant & langganan (F-00, BR-00.3, BR-00.4, BR-00.6). Kunci PascalCase (D-05).
 */
return [
    // BR-00.6: paket trial bila calon tenant tidak memilih paket di halaman harga.
    'KodePaketTrialBawaan' => env('TENANT_PAKET_TRIAL', 'PRO'),

    // BR-00.3: paket tujuan saat trial berakhir tanpa pembayaran.
    'KodePaketGratis' => env('TENANT_PAKET_GRATIS', 'GRATIS'),

    // BR-00.4: percobaan registrasi per IP per jam.
    'BatasRegistrasiPerJam' => 5,

    // BR-00.5: masa berlaku tautan verifikasi email.
    'JamBerlakuVerifikasiEmail' => 24,

    // BR-00.2: slug yang bentrok dengan rute sistem (§13.6).
    'SlugTerlarang' => [
        'daftar', 'masuk', 'keluar', 'lupa-kata-sandi', 'verifikasi-email', 'pilih-tenant', 'kelola', 'unduh',
        'internal', 'api', 'webhook', 'sehat', 's', 'mitra', 'pengelola', 'harga', 'bantuan', 'legal',
        // Auth tenant: rute atur ulang kata sandi (BR-00.9).
        'atur-ulang-kata-sandi',
    ],
];
