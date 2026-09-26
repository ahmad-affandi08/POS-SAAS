<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;

/**
 * Penguji yang melayani beberapa penyedia sekaligus (gerbang pembayaran, WhatsApp; v2.04) sehingga perlu tahu
 * penyedia mana yang diuji. Aturan sama dengan `PengujiKoneksi`: tidak melempar exception, pesan tanpa kredensial.
 */
interface PengujiKoneksiPenyedia
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function UjiPenyedia(string $penyedia, array $pengaturan, array $kredensial): HasilUjiKoneksi;
}
