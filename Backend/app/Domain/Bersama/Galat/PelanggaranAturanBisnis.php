<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Galat;

use RuntimeException;

/**
 * Aksi ditolak karena melanggar aturan bisnis (BR-xx). Pesan berbahasa Indonesia dan aman ditampilkan ke pengguna.
 * Dirender menjadi galat validasi (Inertia) atau `{"Galat": {"Kode", "Pesan", "Detail"}}` (JSON), PRD §16.
 *
 * F-02b: `statusHttp` & `detail` opsional untuk API (misal PIN terkunci 429 dengan sisa detik); bawaan 422 & kosong.
 */
final class PelanggaranAturanBisnis extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public readonly string $kode,
        string $pesan,
        public readonly string $bidang = 'Umum',
        public readonly int $statusHttp = 422,
        public readonly array $detail = [],
    ) {
        parent::__construct($pesan);
    }
}
