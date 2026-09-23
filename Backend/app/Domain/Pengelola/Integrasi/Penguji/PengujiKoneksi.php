<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;

/**
 * Tes koneksi satu penyedia (P-05 langkah 2). Tidak boleh melempar exception; kegagalan dikembalikan sebagai hasil.
 * Pesan galat tidak boleh memuat kredensial (BR-P05.6).
 */
interface PengujiKoneksi
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi;
}
