<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memeriksa secret key Turnstile dengan token uji palsu: Cloudflare menjawab `invalid-input-response` bila secret
 * benar, dan `invalid-input-secret` bila secret salah (P-05, BR-00.4).
 */
final class PengujiTurnstile implements PengujiKoneksi
{
    public const URL_VERIFIKASI = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        try {
            $respons = Http::asForm()->timeout(10)->post(self::URL_VERIFIKASI, [
                'secret' => $kredensial['KunciRahasia'] ?? '',
                'response' => 'uji-koneksi-pengelola',
            ]);
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa menghubungi Cloudflare: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        $kodeGalat = $respons->json('error-codes');
        $kodeGalat = is_array($kodeGalat) ? $kodeGalat : [];

        if (in_array('invalid-input-secret', $kodeGalat, true) || in_array('missing-input-secret', $kodeGalat, true)) {
            return HasilUjiKoneksi::Gagal('Secret key ditolak Cloudflare.');
        }

        if (! $respons->successful()) {
            return HasilUjiKoneksi::Gagal("Cloudflare menjawab HTTP {$respons->status()}.");
        }

        return HasilUjiKoneksi::Berhasil('Secret key diterima Cloudflare.');
    }
}
