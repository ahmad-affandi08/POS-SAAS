<?php

declare(strict_types=1);

namespace App\Domain\Situs\Layanan;

/**
 * Slug halaman situs pemasaran (D-21): huruf kecil, angka, tanda hubung, paling banyak dua segmen (`fitur`,
 * `solusi/kafe-resto`). Segmen pertama tidak boleh memakai jalur sistem agar tidak menaungi rute aplikasi.
 */
final class AturanSlugSitus
{
    public const POLA = '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?';

    public const TERLARANG = [
        'masuk', 'daftar', 'kelola', 'legal', 's', 'api', 'webhook', 'sehat', 'undangan', 'verifikasi-email',
        'lupa-kata-sandi', 'atur-ulang-kata-sandi', 'keluar', 'pilih-tenant', 'kompatibilitas-perangkat', 'gambar-situs',
        'pratinjau-situs', 'peta-situs', 'ganti-kata-sandi', 'build', 'storage', 'meja', 'up', 'unduh-berkas',
    ];

    /** Pesan galat, atau null bila slug boleh dipakai. */
    public static function Periksa(string $slug): ?string
    {
        if (preg_match('#^'.self::POLA.'$#', $slug) !== 1 || strlen($slug) > 100) {
            return 'Slug hanya huruf kecil, angka, dan tanda hubung, paling banyak dua bagian (misal "fitur" atau "solusi/kafe").';
        }

        if (in_array(explode('/', $slug)[0], self::TERLARANG, true)) {
            return 'Slug ini dipakai sistem. Pilih slug lain.';
        }

        return null;
    }
}
