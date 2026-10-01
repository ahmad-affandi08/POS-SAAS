<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

/**
 * F-17 bagian 3: HMAC untuk semua nilai rahasia akun pembeli (nomor HP di tabel kode, kode, token daftar, token sesi,
 * IP). Kunci aplikasi membuat hash nomor HP tidak bisa ditebak ulang dari daftar nomor walau tabelnya bocor.
 */
final class SandiAkunOnline
{
    public static function BuatHash(string $nilai): string
    {
        return hash_hmac('sha256', $nilai, (string) config('app.key'));
    }

    /** Token acak 48 karakter heksadesimal (192 bit) untuk cookie sesi & token daftar. */
    public static function BuatToken(): string
    {
        return bin2hex(random_bytes(24));
    }
}
