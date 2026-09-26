<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Penjualan\Aksi\TerimaNotifikasiQris;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\GalatApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-08 BR-08.5: notifikasi gerbang pembayaran `POST /webhook/{penyedia}` (tanpa login/CSRF, dibatasi laju per IP).
 * Penyedia harus sama dengan gerbang aktif (lainnya 404); tanda tangan/token diverifikasi adaptor (tidak sah = 401).
 * Tagihan tidak dikenal dijawab 200 `{Diterima: false}` agar gerbang berhenti mengulang; lainnya `{Diterima: true}`.
 */
final class WebhookGerbangPembayaranKontroler extends Kontroler
{
    public function Terima(Request $permintaan, string $penyedia, PembuatGerbangPembayaran $pembuat, TerimaNotifikasiQris $terima): JsonResponse
    {
        $gerbang = $pembuat->AmbilAktif();

        if ($gerbang === null || strtolower($gerbang->AmbilKode()) !== $penyedia) {
            return GalatApi::Buat('PenyediaTidakAktif', 'Gerbang pembayaran ini tidak aktif.', 404);
        }

        $notifikasi = $gerbang->UraiWebhook($permintaan);

        if ($notifikasi === null) {
            return GalatApi::Buat('TandaTanganTidakSah', 'Tanda tangan notifikasi tidak sah.', 401);
        }

        return response()->json(['Diterima' => $terima->Jalankan($notifikasi, $gerbang->AmbilKode())]);
    }
}
