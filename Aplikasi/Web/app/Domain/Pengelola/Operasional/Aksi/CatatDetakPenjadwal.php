<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Aksi;

use App\Domain\Pengelola\Operasional\Model\DetakPenjadwal;

/**
 * Detak scheduler (P-11, BR-P11.1): dipanggil perintah terjadwal tiap menit.
 */
final class CatatDetakPenjadwal
{
    public function Jalankan(): DetakPenjadwal
    {
        return DetakPenjadwal::query()->updateOrCreate(['Nama' => DetakPenjadwal::NAMA_UTAMA], ['TerakhirPada' => now()]);
    }
}
