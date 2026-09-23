<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Surel;

use Illuminate\Mail\Mailable;

/**
 * Notifikasi ke Owner saat tenant ditangguhkan manual (P-07, BR-P07.4). Hanya kategori yang dikirim; catatan
 * internal tim tidak pernah disertakan.
 */
final class LanggananDitangguhkan extends Mailable
{
    public function __construct(public readonly string $nama, public readonly string $namaUsaha, public readonly string $kategori)
    {
        $this->subject("Akun {$namaUsaha} ditangguhkan")
            ->text('Surel.Pengelola.LanggananDitangguhkan', [
                'Nama' => $nama,
                'NamaUsaha' => $namaUsaha,
                'Kategori' => $kategori,
                'EmailDukungan' => config('mail.from.address'),
            ]);
    }
}
