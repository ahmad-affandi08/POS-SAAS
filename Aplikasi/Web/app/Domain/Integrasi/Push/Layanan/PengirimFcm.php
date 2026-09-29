<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Push\Layanan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Pengirim Firebase Cloud Messaging HTTP v1 dari konfigurasi Push P-05. */
final class PengirimFcm
{
    /** @param array<string, string> $data */
    public function Kirim(string $token, string $judul, string $isi, array $data): HasilKirimFcm
    {
        $akun = $this->AmbilAkun();

        if ($akun === null) {
            return HasilKirimFcm::TidakAktif();
        }

        try {
            $tokenAkses = $this->AmbilTokenAkses($akun);

            if ($tokenAkses === null) {
                return HasilKirimFcm::Gagal('Token akses FCM tidak bisa dibuat.');
            }

            $respons = Http::withToken($tokenAkses)->timeout(15)->post(
                'https://fcm.googleapis.com/v1/projects/'.rawurlencode($akun['project_id']).'/messages:send',
                ['message' => ['token' => $token, 'notification' => ['title' => $judul, 'body' => $isi], 'data' => $data]],
            );

            if ($respons->successful()) {
                return HasilKirimFcm::Berhasil();
            }

            $kode = (string) ($respons->json('error.details.0.errorCode') ?? '');

            if (in_array($kode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)) {
                return HasilKirimFcm::TokenTidakBerlaku();
            }

            return HasilKirimFcm::Gagal('FCM menolak pesan (HTTP '.$respons->status().').');
        } catch (Throwable) {
            return HasilKirimFcm::Gagal('FCM tidak bisa dihubungi.');
        }
    }

    /** @return array{client_email: string, private_key: string, project_id: string, token_uri: string}|null */
    private function AmbilAkun(): ?array
    {
        $konfigurasi = config('integrasi.Push');
        $mentah = is_array($konfigurasi) ? ($konfigurasi['Kredensial']['AkunLayanan'] ?? null) : null;
        $akun = is_string($mentah) ? json_decode($mentah, true) : null;

        if (! is_array($akun)) {
            return null;
        }

        foreach (['client_email', 'private_key', 'project_id'] as $kunci) {
            if (! is_string($akun[$kunci] ?? null) || $akun[$kunci] === '') {
                return null;
            }
        }

        return [
            'client_email' => $akun['client_email'],
            'private_key' => $akun['private_key'],
            'project_id' => $akun['project_id'],
            'token_uri' => is_string($akun['token_uri'] ?? null) && $akun['token_uri'] !== ''
                ? $akun['token_uri']
                : 'https://oauth2.googleapis.com/token',
        ];
    }

    /** @param array{client_email: string, private_key: string, project_id: string, token_uri: string} $akun */
    private function AmbilTokenAkses(array $akun): ?string
    {
        return Cache::remember('fcm-token:'.hash('sha256', $akun['client_email']), 3300, function () use ($akun): ?string {
            $sekarang = time();
            $kode = static fn (string $nilai): string => rtrim(strtr(base64_encode($nilai), '+/', '-_'), '=');
            $bahan = $kode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)).'.'.$kode(json_encode([
                'iss' => $akun['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $akun['token_uri'],
                'iat' => $sekarang,
                'exp' => $sekarang + 3600,
            ], JSON_THROW_ON_ERROR));
            $kunci = openssl_pkey_get_private($akun['private_key']);
            $tanda = '';

            if ($kunci === false || ! openssl_sign($bahan, $tanda, $kunci, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $jwt = $bahan.'.'.$kode($tanda);
            $respons = Http::asForm()->timeout(10)->post($akun['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            $token = $respons->successful() ? $respons->json('access_token') : null;

            return is_string($token) && $token !== '' ? $token : null;
        });
    }
}

final readonly class HasilKirimFcm
{
    private function __construct(
        public bool $berhasil,
        public bool $aktif,
        public bool $tokenTidakBerlaku,
        public ?string $pesan,
    ) {}

    public static function Berhasil(): self
    {
        return new self(true, true, false, null);
    }

    public static function TidakAktif(): self
    {
        return new self(false, false, false, null);
    }

    public static function TokenTidakBerlaku(): self
    {
        return new self(false, true, true, 'Token perangkat tidak berlaku.');
    }

    public static function Gagal(string $pesan): self
    {
        return new self(false, true, false, $pesan);
    }
}
