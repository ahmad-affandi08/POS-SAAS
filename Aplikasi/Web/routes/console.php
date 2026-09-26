<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * Jadwal tugas. Di Hostinger dijalankan oleh cron `* * * * * php artisan schedule:run` (PRD §14).
 */

// BR-P02.4: pengingat hari libur tahun berikutnya (aktif mulai 1 November sampai terbit).
Schedule::command('pengelola:ingatkan-hari-libur')->dailyAt('08:00')->timezone('Asia/Jakarta');

// BR-P05.3: uji koneksi integrasi aktif setiap jam; alert ke Teknis saat baru gagal.
Schedule::command('pengelola:uji-integrasi')->hourly()->withoutOverlapping();

// BR-00.3: trial yang berakhir turun ke paket Gratis.
Schedule::command('tenant:akhiri-trial')->hourly()->withoutOverlapping();

// P-08: tagihan lewat jatuh tempo, langganan Tertunggak lalu Ditangguhkan setelah masa tenggang.
Schedule::command('tagihan:proses-tunggakan')->hourly()->withoutOverlapping();

// BR-P06.5: pengumuman versi materiil dokumen legal ke Owner selama masa pengumuman (sekali per versi per pengguna).
// v1.98 HCL: daftar kompatibilitas perangkat dari hasil Wizard Uji Perangkat.
Schedule::command('pengelola:segarkan-kompatibilitas')->dailyAt('02:30')->timezone('Asia/Jakarta')->withoutOverlapping();

Schedule::command('tenant:umumkan-dokumen-legal')->dailyAt('09:00')->timezone('Asia/Jakarta')->withoutOverlapping();

// P-11 BR-P11.1: detak scheduler tiap menit + pemeriksaan alert operasional (scheduler, antrean, backup).
Schedule::command('pengelola:detak')->everyMinute()->withoutOverlapping();

// P-11 (§14.4): worker antrean database dijalankan scheduler tiap menit di Hostinger (tanpa proses daemon).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// P-09: tiket selesai yang tidak dibuka lagi dalam 7 hari ditutup otomatis.
Schedule::command('pengelola:tutup-tiket-selesai')->dailyAt('01:00')->timezone('Asia/Jakarta')->withoutOverlapping();

// F-05a (DesainF05a C.9): pemeriksaan malam SaldoStok = Σ MutasiStok, rantai mutasi, lapisan FIFO, batch & seri (keluar 1 bila berbeda).
Schedule::command('persediaan:bangun-ulang-saldo --periksa')->dailyAt('02:30')->timezone('Asia/Jakarta')->withoutOverlapping();

// F-14a: bangun ulang ringkasan penjualan harian H-1 & H-2 (penjualan offline terlambat, job antrean gagal).
Schedule::command('laporan:bangun-ulang-ringkasan')->dailyAt('02:45')->timezone('Asia/Jakarta')->withoutOverlapping();

// F-16b: poin kedaluwarsa dihanguskan (FIFO) lalu tier pelanggan dievaluasi dari belanja N bulan terakhir.
Schedule::command('pelanggan:proses-loyalti')->dailyAt('03:00')->timezone('Asia/Jakarta')->withoutOverlapping();
