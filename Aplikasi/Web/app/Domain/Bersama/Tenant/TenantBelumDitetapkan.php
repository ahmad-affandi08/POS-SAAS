<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use RuntimeException;

/**
 * Dilempar saat data tenant diakses tanpa tenant aktif.
 * Sengaja "gagal tertutup": lebih baik error daripada data tenant lain bocor.
 */
final class TenantBelumDitetapkan extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant aktif belum ditetapkan. Data tenant hanya boleh diakses dalam konteks tenant.');
    }
}
