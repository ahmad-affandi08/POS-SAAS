<?php

declare(strict_types=1);

use App\Http\Kontroler\Publik\WebhookGerbangPembayaranKontroler;
use Illuminate\Support\Facades\Route;

/*
 * Webhook masuk dari layanan luar (PRD §16.6), didaftarkan dari bootstrap/app.php dengan grup `api` (tanpa sesi/CSRF).
 * Keaslian diverifikasi per penyedia (tanda tangan/token), bukan lewat login.
 */

// F-08 BR-08.5: notifikasi gerbang pembayaran QRIS dinamis (kode adaptor huruf kecil).
Route::post('/webhook/{penyedia}', [WebhookGerbangPembayaranKontroler::class, 'Terima'])
    ->where('penyedia', 'midtrans|xendit|tripay|duitku|ipaymu|doku')
    ->middleware('throttle:webhook')
    ->name('webhook.gerbang-pembayaran');
