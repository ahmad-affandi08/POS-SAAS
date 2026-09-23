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

// P-11 BR-P11.1: detak scheduler tiap menit + pemeriksaan alert operasional (scheduler, antrean, backup).
Schedule::command('pengelola:detak')->everyMinute()->withoutOverlapping();

// P-11 (§14.4): worker antrean database dijalankan scheduler tiap menit di Hostinger (tanpa proses daemon).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// P-09: tiket selesai yang tidak dibuka lagi dalam 7 hari ditutup otomatis.
Schedule::command('pengelola:tutup-tiket-selesai')->dailyAt('01:00')->timezone('Asia/Jakarta')->withoutOverlapping();
