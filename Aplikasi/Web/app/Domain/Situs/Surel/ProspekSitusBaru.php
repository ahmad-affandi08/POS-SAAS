<?php

declare(strict_types=1);

namespace App\Domain\Situs\Surel;

use App\Domain\Bersama\Surel\SurelDasar;
use Illuminate\Bus\Queueable;

/**
 * Email ke tim pengelola saat ada prospek baru dari situs (§13.9 bagian B). Isi hanya nama, usaha, jenis, kota, dan
 * tautan ke konsol; nomor HP & email pengunjung dibaca di konsol (tidak ikut antrean & kotak surat).
 */
final class ProspekSitusBaru extends SurelDasar
{
    use Queueable;

    public function __construct(
        public readonly string $jenis,
        public readonly string $nama,
        public readonly ?string $namaUsaha,
        public readonly ?string $jenisUsaha,
        public readonly ?string $kota,
        public readonly string $tautan,
    ) {
        $this->subject("Prospek baru: {$jenis} dari {$nama}")
            ->IsiSurel('Pengelola.ProspekSitusBaru', [
                'Jenis' => $jenis, 'Nama' => $nama, 'NamaUsaha' => $namaUsaha ?? '-', 'JenisUsaha' => $jenisUsaha ?? '-',
                'Kota' => $kota ?? '-', 'Tautan' => $tautan,
            ]);
    }
}
