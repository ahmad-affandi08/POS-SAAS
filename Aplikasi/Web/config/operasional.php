<?php

declare(strict_types=1);

/*
 * Monitoring operasional (P-11, PGL-20, §20.3). Kunci PascalCase (D-05).
 */
return [
    // BR-P11.1: scheduler tidak berdetak lebih dari 3 menit = alert kritis.
    'BatasDetakPenjadwalMenit' => 3,

    // BR-P11.1: umur job antrean tertua lebih dari 5 menit = alert kritis.
    'BatasUmurTugasTertuaMenit' => 5,

    // §14.5: backup harian (RPO ≤ 24 jam) + toleransi 2 jam.
    'BatasUmurBackupJam' => 26,

    // Jumlah job gagal terbaru yang ditampilkan di dasbor.
    'JumlahTugasGagalDitampilkan' => 50,
];
