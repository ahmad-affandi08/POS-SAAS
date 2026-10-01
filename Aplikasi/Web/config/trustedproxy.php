<?php

declare(strict_types=1);

/*
 * Proksi tepercaya (audit PAY-P1-05). Di balik Cloudflare/reverse proxy, alamat IP pengguna, skema (https), dan host asli
 * hanya datang lewat header `X-Forwarded-*`, dan header itu hanya boleh dipercaya bila dikirim proksi milik kita:
 * kalau dipercaya dari siapa saja, IP bisa dipalsukan (melewati batas laju & log audit); kalau tidak dipercaya sama
 * sekali, semua pengguna tampak berasal dari IP proksi (batas laju saling mengunci, audit salah) dan `isSecure()` salah
 * sehingga HSTS & tautan bertanda tangan ikut salah.
 *
 * `PROKSI_TEPERCAYA`: kosong = tidak ada proksi (bawaan; perilaku lama, aman untuk lokal), `*` = percaya pada pemanggil
 * langsung (hanya bila server TIDAK bisa dijangkau selain lewat proksi), atau daftar IP/CIDR dipisah koma, misalnya
 * rentang Cloudflare dari https://www.cloudflare.com/ips/. Lihat Panduan/PasangDiHosting.md.
 *
 * Dibaca framework (`TrustProxies`) sebagai cadangan bila tidak diatur lewat kode, dan dicache aman lewat `config:cache`.
 */
return [
    'proxies' => env('PROKSI_TEPERCAYA') === null || env('PROKSI_TEPERCAYA') === '' ? null : env('PROKSI_TEPERCAYA'),
];
