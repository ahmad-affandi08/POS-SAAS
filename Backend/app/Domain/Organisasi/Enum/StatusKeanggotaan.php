<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Status keanggotaan pengguna di satu tenant (`TenantPengguna.Status`). Undangan & nonaktif ditambahkan F-02.
 */
enum StatusKeanggotaan: string
{
    case Aktif = 'Aktif';
}
