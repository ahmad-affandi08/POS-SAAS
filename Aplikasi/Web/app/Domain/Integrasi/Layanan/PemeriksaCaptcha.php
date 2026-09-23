<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Layanan;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifikasi token Cloudflare Turnstile dari formulir publik (BR-00.4). Kunci dibaca dari konfigurasi aktif P-05
 * (`config('integrasi.Turnstile')`). Tanpa kunci: lolos di lingkungan non-produksi, ditolak di produksi.
 */
final class PemeriksaCaptcha
{
    public const URL_VERIFIKASI = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function CekAktif(): bool
    {
        return filled(config('integrasi.Turnstile.KunciRahasia'));
    }

    public function Periksa(?string $token, ?string $ip): bool
    {
        if (! $this->CekAktif()) {
            return ! app()->isProduction();
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $respons = Http::asForm()->timeout(10)->post(self::URL_VERIFIKASI, [
                'secret' => (string) config('integrasi.Turnstile.KunciRahasia'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $galat) {
            Log::warning('Verifikasi CAPTCHA gagal dihubungi.', ['Pesan' => $galat->getMessage()]);

            return false;
        }

        return $respons->json('success') === true;
    }
}
