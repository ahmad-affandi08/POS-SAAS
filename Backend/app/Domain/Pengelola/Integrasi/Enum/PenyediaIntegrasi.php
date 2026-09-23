<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

use App\Domain\Pengelola\Integrasi\Penguji\PengujiKoneksi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiS3;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiSmtp;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiTurnstile;

/**
 * Penyedia layanan per jenis integrasi beserta bidang isiannya. Bidang pengaturan tidak rahasia dan boleh tampil;
 * bidang kredensial disimpan terenkripsi dan tidak pernah ditampilkan ulang (BR-P05.1).
 */
enum PenyediaIntegrasi: string
{
    case Smtp = 'Smtp';
    case Turnstile = 'Turnstile';
    case S3 = 'S3';

    /**
     * @return list<array{Kunci: string, Label: string, Jenis: string, Wajib: bool, Opsi?: list<string>, Keterangan?: string}>
     */
    public function AmbilBidangPengaturan(): array
    {
        return match ($this) {
            self::Smtp => [
                ['Kunci' => 'Host', 'Label' => 'Host SMTP', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Misal smtp.hostinger.com'],
                ['Kunci' => 'Port', 'Label' => 'Port', 'Jenis' => 'Angka', 'Wajib' => true, 'Keterangan' => '465 untuk SSL, 587 untuk TLS'],
                ['Kunci' => 'Enkripsi', 'Label' => 'Enkripsi', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['Ssl', 'Tls']],
                ['Kunci' => 'NamaPengguna', 'Label' => 'Nama pengguna', 'Jenis' => 'Teks', 'Wajib' => true],
                ['Kunci' => 'AlamatPengirim', 'Label' => 'Alamat pengirim', 'Jenis' => 'Email', 'Wajib' => true],
                ['Kunci' => 'NamaPengirim', 'Label' => 'Nama pengirim', 'Jenis' => 'Teks', 'Wajib' => true],
            ],
            self::Turnstile => [
                ['Kunci' => 'KunciSitus', 'Label' => 'Site key', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Kunci publik yang dipasang di halaman registrasi'],
            ],
            self::S3 => [
                ['Kunci' => 'Endpoint', 'Label' => 'Endpoint', 'Jenis' => 'Url', 'Wajib' => true, 'Keterangan' => 'Misal https://<akun>.r2.cloudflarestorage.com'],
                ['Kunci' => 'Wilayah', 'Label' => 'Region', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Cloudflare R2: auto'],
                ['Kunci' => 'Bucket', 'Label' => 'Bucket', 'Jenis' => 'Teks', 'Wajib' => true],
            ],
        };
    }

    /**
     * @return list<array{Kunci: string, Label: string}>
     */
    public function AmbilBidangKredensial(): array
    {
        return match ($this) {
            self::Smtp => [['Kunci' => 'KataSandi', 'Label' => 'Kata sandi SMTP']],
            self::Turnstile => [['Kunci' => 'KunciRahasia', 'Label' => 'Secret key']],
            self::S3 => [
                ['Kunci' => 'IdKunciAkses', 'Label' => 'Access key ID'],
                ['Kunci' => 'KunciAksesRahasia', 'Label' => 'Secret access key'],
            ],
        };
    }

    /**
     * @return class-string<PengujiKoneksi>
     */
    public function AmbilKelasPenguji(): string
    {
        return match ($this) {
            self::Smtp => PengujiSmtp::class,
            self::Turnstile => PengujiTurnstile::class,
            self::S3 => PengujiS3::class,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Turnstile => 'Cloudflare Turnstile',
            self::S3 => 'S3-compatible (misal Cloudflare R2)',
        };
    }
}
