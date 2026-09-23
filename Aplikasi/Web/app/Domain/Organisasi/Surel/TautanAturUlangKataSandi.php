<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use Illuminate\Mail\Mailable;

/**
 * Tautan atur ulang kata sandi akun tenant (F-00, BR-00.9).
 */
final class TautanAturUlangKataSandi extends Mailable
{
    public function __construct(public readonly string $nama, public readonly string $tautan, public readonly int $menitBerlaku)
    {
        $this->subject('Atur ulang kata sandi akun Anda')
            ->text('Surel.Tenant.TautanAturUlangKataSandi', ['Nama' => $nama, 'Tautan' => $tautan, 'MenitBerlaku' => $menitBerlaku]);
    }
}
