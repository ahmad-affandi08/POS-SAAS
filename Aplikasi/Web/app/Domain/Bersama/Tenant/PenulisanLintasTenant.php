<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use LogicException;

/**
 * Baris milik satu tenant hendak ditulis dengan `IdTenant` tenant lain padahal konteks aktif tenant yang berbeda
 * (audit PAY-P1-03). Hampir pasti bug (mass assignment `IdTenant` dari masukan, atau Id tenant salah di aksi/impor).
 */
final class PenulisanLintasTenant extends LogicException
{
    public static function Buat(string $model, int $idTenantAktif, int $idTenantDiberikan): self
    {
        return new self("Penulisan lintas tenant ditolak: {$model} dengan IdTenant {$idTenantDiberikan} saat konteks tenant {$idTenantAktif}.");
    }
}
