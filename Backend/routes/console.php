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
