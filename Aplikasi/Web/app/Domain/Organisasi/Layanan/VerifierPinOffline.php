<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use RuntimeException;

/**
 * PIN kasir offline (PRD §25.2 no. 3). Verifier = Argon2id(PIN, garam 16 byte) 32 byte, dihitung sekali saat PIN
 * diatur (libsodium `crypto_pwhash` Argon2id v1.3, paralelisme 1). Untuk perangkat, verifier dibungkus AES-256-GCM
 * dengan kunci per perangkat (`Perangkat.KunciPinOffline`) sehingga salinan basis data perangkat saja tidak cukup
 * untuk menebak PIN. Aplikasi menghitung Argon2id yang sama (parameter `PARAMETER`) lalu membandingkan waktu-konstan.
 * Nilai di sini wajib sama dengan `Spesifikasi/VektorUjiPin/` (diuji di PHP & Dart).
 */
final class VerifierPinOffline
{
    public const ITERASI = 2;

    public const MEMORI_KIB = 19456;

    public const PANJANG = 32;

    /**
     * @return array{Algoritme: string, Iterasi: int, MemoriKiB: int, Paralelisme: int, Panjang: int}
     */
    public static function AmbilParameter(): array
    {
        return ['Algoritme' => 'Argon2id', 'Iterasi' => self::ITERASI, 'MemoriKiB' => self::MEMORI_KIB, 'Paralelisme' => 1, 'Panjang' => self::PANJANG];
    }

    /** "garamBase64$hashBase64" untuk disimpan (terenkripsi) di `TenantPengguna.VerifierPinOffline`. */
    public static function Buat(string $pin, ?string $garam = null): string
    {
        $garam ??= random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

        return base64_encode($garam).'$'.base64_encode(self::HitungHash($pin, $garam));
    }

    public static function HitungHash(string $pin, string $garam): string
    {
        return sodium_crypto_pwhash(self::PANJANG, $pin, $garam, self::ITERASI, self::MEMORI_KIB * 1024, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    public static function BuatKunciPerangkat(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Verifier terbungkus kunci perangkat untuk data awal: `{Garam, Nonce, Sandi}` (base64; `Sandi` = ciphertext ‖ tag
     * GCM 16 byte).
     *
     * @return array{Garam: string, Nonce: string, Sandi: string}
     */
    public static function Bungkus(string $verifier, string $kunciPerangkatBase64): array
    {
        [$garam, $hash] = explode('$', $verifier, 2) + [1 => ''];
        $kunci = base64_decode($kunciPerangkatBase64, true);
        $isi = base64_decode($hash, true);

        if ($kunci === false || strlen($kunci) !== 32 || $isi === false) {
            throw new RuntimeException('Verifier atau kunci PIN perangkat rusak.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $sandi = openssl_encrypt($isi, 'aes-256-gcm', $kunci, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($sandi === false) {
            throw new RuntimeException('Gagal membungkus verifier PIN.');
        }

        return ['Garam' => $garam, 'Nonce' => base64_encode($nonce), 'Sandi' => base64_encode($sandi.$tag)];
    }
}
