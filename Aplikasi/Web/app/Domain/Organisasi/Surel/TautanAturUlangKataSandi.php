<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Tautan atur ulang kata sandi akun tenant (F-00, BR-00.9).
 */
final class TautanAturUlangKataSandi extends SurelDasar
{
    public function __construct(public readonly string $nama, public readonly string $tautan, public readonly int $menitBerlaku)
    {
        $this->subject('Atur ulang kata sandi akun Anda')
            ->IsiSurel('Tenant.TautanAturUlangKataSandi', ['Nama' => $nama, 'Tautan' => $tautan, 'MenitBerlaku' => $menitBerlaku]);
    }
}
