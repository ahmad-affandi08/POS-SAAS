<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Tautan verifikasi email akun tenant (F-00 langkah 2, BR-00.5).
 */
final class VerifikasiEmail extends SurelDasar
{
    public function __construct(public readonly string $nama, public readonly string $tautan, public readonly int $jamBerlaku)
    {
        $this->subject('Verifikasi email akun Anda')
            ->IsiSurel('Tenant.VerifikasiEmail', ['Nama' => $nama, 'Tautan' => $tautan, 'JamBerlaku' => $jamBerlaku]);
    }
}
