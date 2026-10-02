<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Layanan;

/**
 * Tautan persetujuan estimasi servis `/{slugTenant}/servis/{token}` (§9.10, "persetujuan pelanggan via WA link"):
 * token acak 40 karakter, yang dicari hanya hash sha256-nya (pola portal kurir v3.49). IP pemberi keputusan disimpan
 * sebagai hash berkunci aplikasi, cukup untuk mencocokkan dua keputusan tanpa menyimpan IP mentah.
 */
final class TautanPersetujuanServis
{
    public const PANJANG_TOKEN = 40;

    public const HARI_BERLAKU = 7;

    public const POLA_TOKEN = '[A-Za-z0-9]{40}';

    public static function Hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function HashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public static function Buat(string $slugTenant, string $token): string
    {
        return url("/{$slugTenant}/servis/{$token}");
    }
}
