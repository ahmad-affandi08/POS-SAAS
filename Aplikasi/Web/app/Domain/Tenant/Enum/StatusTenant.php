<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status tenant (F-00). Status penghapusan data (UU PDP) ditambahkan bersama P-07.
 */
enum StatusTenant: string
{
    case Aktif = 'Aktif';
}
