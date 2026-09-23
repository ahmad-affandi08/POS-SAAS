<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Galat;

use RuntimeException;

/**
 * Aksi ditolak karena melanggar aturan bisnis (BR-xx). Pesan berbahasa Indonesia dan aman ditampilkan ke pengguna.
 * Dirender menjadi galat validasi (Inertia) atau `{"Galat": {"Kode", "Pesan"}}` (JSON), PRD §16.
 */
final class PelanggaranAturanBisnis extends RuntimeException
{
    public function __construct(
        public readonly string $kode,
        string $pesan,
        public readonly string $bidang = 'Umum',
    ) {
        parent::__construct($pesan);
    }
}
