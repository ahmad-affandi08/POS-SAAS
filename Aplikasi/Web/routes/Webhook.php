<?php

declare(strict_types=1);

use App\Domain\Integrasi\Layanan\PencariGerbangWebhook;
use App\Http\Kontroler\Publik\WebhookGerbangPembayaranKontroler;
use Illuminate\Support\Facades\Route;

/*
 * Webhook masuk dari layanan luar (PRD §16.6), didaftarkan dari bootstrap/app.php dengan grup `api` (tanpa sesi/CSRF).
 * Keaslian diverifikasi per penyedia (tanda tangan/token), bukan lewat login.
 */

$penyedia = 'midtrans|xendit|tripay|duitku|ipaymu|doku';

// F-08 BR-08.5, v2.06: notifikasi gerbang pembayaran QRIS dinamis milik tenant (kode adaptor huruf kecil + token
// webhook tenant). URL ini ditampilkan di back-office tenant untuk disalin ke dasbor penyedia.
Route::post('/webhook/{penyedia}/{tokenWebhook}', [WebhookGerbangPembayaranKontroler::class, 'TerimaTenant'])
    ->where(['penyedia' => $penyedia, 'tokenWebhook' => PencariGerbangWebhook::POLA_TOKEN])
    ->middleware('throttle:webhook')
    ->name('webhook.gerbang-pembayaran.tenant');

// Rute lama gerbang tingkat platform (sebelum v2.06): selalu 404 `PenyediaTidakAktif`.
Route::post('/webhook/{penyedia}', [WebhookGerbangPembayaranKontroler::class, 'Terima'])
    ->where('penyedia', $penyedia)
    ->middleware('throttle:webhook')
    ->name('webhook.gerbang-pembayaran');
