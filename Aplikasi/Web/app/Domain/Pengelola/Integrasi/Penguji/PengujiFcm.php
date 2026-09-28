<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Menguji akun layanan Firebase (P-05, OWN-03) dengan menukarnya menjadi token akses OAuth2 di Google.
 *
 * Penukaran token adalah uji yang paling jujur tanpa mengganggu siapa pun: ia membuktikan berkas JSON utuh, kunci
 * privatnya cocok dengan `client_email`, dan jam server tidak melenceng — tanpa mengirim notifikasi ke perangkat mana
 * pun. Mengirim pesan uji ke FCM justru butuh token perangkat sungguhan, yang belum tentu ada saat integrasi diatur.
 */
final class PengujiFcm implements PengujiKoneksi
{
    /** Cakupan minimum untuk mengirim pesan lewat FCM HTTP v1. */
    public const CAKUPAN = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Umur JWT penukaran; Google menolak lebih dari satu jam. */
    private const UMUR_DETIK = 3600;

    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $akun = json_decode($kredensial['AkunLayanan'] ?? '', true);

        if (! is_array($akun)) {
            return HasilUjiKoneksi::Gagal('Isi akun layanan bukan JSON yang sah. Salin seluruh isi berkas dari Firebase, termasuk kurung kurawalnya.');
        }

        foreach (['client_email', 'private_key', 'project_id'] as $wajib) {
            if (! is_string($akun[$wajib] ?? null) || $akun[$wajib] === '') {
                return HasilUjiKoneksi::Gagal("Akun layanan tidak memuat `{$wajib}`. Pastikan berkasnya dari Setelan proyek → Akun layanan, bukan `google-services.json`.");
            }
        }

        $alamatToken = is_string($akun['token_uri'] ?? null) && $akun['token_uri'] !== ''
            ? $akun['token_uri']
            : 'https://oauth2.googleapis.com/token';

        $jwt = self::BuatJwt($akun, $alamatToken);

        if ($jwt === null) {
            return HasilUjiKoneksi::Gagal('Kunci privat di akun layanan tidak bisa dipakai menandatangani. Berkasnya kemungkinan terpotong saat disalin.');
        }

        try {
            $respons = Http::asForm()->timeout(10)->post($alamatToken, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa menghubungi Google: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        if (! $respons->successful()) {
            $sebab = $respons->json('error_description');
            $sebab = is_string($sebab) && $sebab !== '' ? $sebab : "HTTP {$respons->status()}";

            return HasilUjiKoneksi::Gagal("Google menolak akun layanan ({$sebab}). Periksa apakah kuncinya sudah dicabut di Firebase.");
        }

        if (! is_string($respons->json('access_token'))) {
            return HasilUjiKoneksi::Gagal('Google menjawab tanpa token akses.');
        }

        return HasilUjiKoneksi::Berhasil("Akun layanan diterima Google untuk proyek {$akun['project_id']}.");
    }

    /**
     * JWT bertanda tangan RS256 sesuai alur akun layanan Google. Dibuat sendiri, bukan lewat paket: satu-satunya yang
     * dibutuhkan adalah `openssl_sign`, sedangkan menambah SDK Google menarik puluhan dependensi baru.
     *
     * @param  array<string, mixed>  $akun
     */
    private static function BuatJwt(array $akun, string $alamatToken): ?string
    {
        $sekarang = time();
        $kepala = ['alg' => 'RS256', 'typ' => 'JWT'];
        $isi = [
            'iss' => $akun['client_email'],
            'scope' => self::CAKUPAN,
            'aud' => $alamatToken,
            'iat' => $sekarang,
            'exp' => $sekarang + self::UMUR_DETIK,
        ];

        $bahan = self::KodeBase64Url(json_encode($kepala, JSON_THROW_ON_ERROR))
            .'.'.self::KodeBase64Url(json_encode($isi, JSON_THROW_ON_ERROR));

        $kunci = openssl_pkey_get_private((string) $akun['private_key']);

        if ($kunci === false) {
            return null;
        }

        $tanda = '';

        if (! openssl_sign($bahan, $tanda, $kunci, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $bahan.'.'.self::KodeBase64Url($tanda);
    }

    private static function KodeBase64Url(string $isi): string
    {
        return rtrim(strtr(base64_encode($isi), '+/', '-_'), '=');
    }
}
