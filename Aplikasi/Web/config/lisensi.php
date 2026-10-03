<?php

declare(strict_types=1);

/*
 * Edisi aplikasi & lisensi pasang sendiri (D-35, PRD §13.10). Kunci konfigurasi PascalCase (D-05).
 *
 * - `Edisi`: `Saas` (bawaan; payou.id + dashboard + konsol) atau `Lisensi` (pembeli memasang dashboard di server &
 *   domainnya sendiri: tanpa Platform Pengelola, situs pemasaran, pendaftaran publik, maupun tagihan langganan).
 * - `KunciPublik`: kunci publik Ed25519 penerbit lisensi PAYOU (base64), dibuat sekali dengan `lisensi:buat-kunci`.
 *   Sengaja ditulis di berkas ini, bukan `.env`: kunci publik bukan rahasia dan ikut dirilis bersama kode. Kunci
 *   privatnya TIDAK PERNAH masuk repo, server pembeli, maupun log; hanya dipakai `lisensi:terbitkan` di mesin PAYOU.
 */
return [
    'Edisi' => env('EDISI', 'Saas'),

    'KunciPublik' => '',
];
