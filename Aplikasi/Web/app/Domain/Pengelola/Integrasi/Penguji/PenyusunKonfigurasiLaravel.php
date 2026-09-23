<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

/**
 * Menerjemahkan isian integrasi ke bentuk konfigurasi Laravel (dipakai penguji dan penerap konfigurasi).
 */
final class PenyusunKonfigurasiLaravel
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     * @return array<string, mixed>
     */
    public static function DiskS3(array $pengaturan, array $kredensial): array
    {
        return [
            'driver' => 's3',
            'key' => $kredensial['IdKunciAkses'] ?? '',
            'secret' => $kredensial['KunciAksesRahasia'] ?? '',
            'region' => (string) ($pengaturan['Wilayah'] ?? 'auto'),
            'bucket' => (string) ($pengaturan['Bucket'] ?? ''),
            'endpoint' => (string) ($pengaturan['Endpoint'] ?? ''),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ];
    }

    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     * @return array<string, mixed>
     */
    public static function MailerSmtp(array $pengaturan, array $kredensial): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => ($pengaturan['Enkripsi'] ?? '') === 'Ssl' ? 'smtps' : 'smtp',
            'host' => (string) ($pengaturan['Host'] ?? ''),
            'port' => (int) ($pengaturan['Port'] ?? 587),
            'username' => (string) ($pengaturan['NamaPengguna'] ?? ''),
            'password' => $kredensial['KataSandi'] ?? '',
            'timeout' => 10,
        ];
    }
}
