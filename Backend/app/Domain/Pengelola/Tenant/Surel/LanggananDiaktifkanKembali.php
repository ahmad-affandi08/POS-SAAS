<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Surel;

use Illuminate\Mail\Mailable;

/**
 * Notifikasi ke Owner saat penangguhan dicabut (P-07, BR-P07.5).
 */
final class LanggananDiaktifkanKembali extends Mailable
{
    public function __construct(public readonly string $nama, public readonly string $namaUsaha, public readonly string $status)
    {
        $this->subject("Akun {$namaUsaha} aktif kembali")
            ->text('Surel.Pengelola.LanggananDiaktifkanKembali', ['Nama' => $nama, 'NamaUsaha' => $namaUsaha, 'Status' => $status]);
    }
}
