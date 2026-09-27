<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Notifikasi ke Owner saat penangguhan dicabut (P-07, BR-P07.5).
 */
final class LanggananDiaktifkanKembali extends SurelDasar
{
    public function __construct(public readonly string $nama, public readonly string $namaUsaha, public readonly string $status)
    {
        $this->subject("Akun {$namaUsaha} aktif kembali")
            ->IsiSurel('Pengelola.LanggananDiaktifkanKembali', ['Nama' => $nama, 'NamaUsaha' => $namaUsaha, 'Status' => $status]);
    }
}
