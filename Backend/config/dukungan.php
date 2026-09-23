<?php

declare(strict_types=1);

/*
 * Tiket dukungan (P-09, PGL-15). Kunci PascalCase (D-05).
 */
return [
    // SLA respons pertama dalam jam kalender, per kode paket lalu per prioritas (P-09 "SLA per paket").
    // Kolom Normal = janji SLA paket (Gratis 2 hari, Starter 1 hari, Pro 8 jam, Bisnis 4 jam). Mendesak/Tinggi
    // hanya mempercepat untuk paket berbayar; Rendah tidak memperlambat janji paket. Jam kerja & hari libur: Fase 2.
    'SlaResponsPertamaJam' => [
        'GRATIS' => ['Mendesak' => 48, 'Tinggi' => 48, 'Normal' => 48, 'Rendah' => 48],
        'STARTER' => ['Mendesak' => 8, 'Tinggi' => 24, 'Normal' => 24, 'Rendah' => 24],
        'PRO' => ['Mendesak' => 4, 'Tinggi' => 8, 'Normal' => 8, 'Rendah' => 8],
        'BISNIS' => ['Mendesak' => 2, 'Tinggi' => 4, 'Normal' => 4, 'Rendah' => 4],
        'ENTERPRISE' => ['Mendesak' => 2, 'Tinggi' => 4, 'Normal' => 4, 'Rendah' => 4],
    ],

    // Dipakai bila paket tenant tidak dikenal (misal paket baru belum diatur di atas).
    'SlaResponsPertamaJamBawaan' => ['Mendesak' => 48, 'Tinggi' => 48, 'Normal' => 48, 'Rendah' => 48],

    // P-09: tiket `Selesai` bisa dibuka lagi dalam 7 hari; setelah itu ditutup otomatis.
    'HariBukaUlang' => 7,

    // Lampiran pesan: disimpan di disk privat, tidak pernah punya URL publik.
    'DiskLampiran' => 'local',
    'MaksimalLampiranPerPesan' => 3,
    'UkuranMaksimalLampiranKb' => 5120,
    'EkstensiLampiran' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'csv'],
];
