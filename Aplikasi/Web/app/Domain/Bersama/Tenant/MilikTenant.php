<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Dipakai setiap model yang tabelnya memiliki kolom `IdTenant` (PRD §13.4).
 *
 * - Semua query otomatis dibatasi ke tenant aktif (LingkupTenant).
 * - `IdTenant` diisi otomatis dari KonteksTenant saat membuat data.
 * - Melewati scope hanya boleh di Domain/Pengelola lewat KonteksPengelola (CLAUDE.md #11).
 */
trait MilikTenant
{
    public static function bootMilikTenant(): void
    {
        static::addGlobalScope(new LingkupTenant);

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('IdTenant'))) {
                $model->setAttribute('IdTenant', app(KonteksTenant::class)->Wajib());
            }
        });
    }
}
