<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Pemberitahuan bahwa kata sandi baru saja diatur ulang (F-00, BR-00.9), agar pemilik akun tahu bila bukan dirinya.
 */
final class KataSandiDiubah extends SurelDasar
{
    public function __construct(public readonly string $nama)
    {
        $this->subject('Kata sandi akun Anda baru saja diubah')
            ->IsiSurel('Tenant.KataSandiDiubah', ['Nama' => $nama, 'TautanLupa' => route('lupa-kata-sandi')]);
    }
}
