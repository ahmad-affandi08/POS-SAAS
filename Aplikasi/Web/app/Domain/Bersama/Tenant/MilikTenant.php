<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
            $konteks = app(KonteksTenant::class);
            $diberikan = $model->getAttribute('IdTenant');

            if (blank($diberikan)) {
                $model->setAttribute('IdTenant', $konteks->Wajib());

                return;
            }

            // Audit PAY-P1-03: `IdTenant` yang diisi manual dan berbeda dari konteks aktif hampir pasti bug (mass
            // assignment dari masukan, atau Id salah). Selalu dicatat kritis; ditolak bila `tenant.TolakIdTenantBerbeda`
            // menyala (bertahap: catat dulu di produksi, tolak setelah log bersih). Tanpa konteks (Platform Pengelola,
            // job lintas tenant) tidak ada yang dibandingkan.
            $aktif = $konteks->Ambil();

            if ($aktif !== null && (int) $diberikan !== $aktif) {
                Log::critical('Penulisan lintas tenant terdeteksi.', ['Model' => $model::class, 'IdTenantAktif' => $aktif, 'IdTenantDiberikan' => (int) $diberikan]);

                if (config('tenant.TolakIdTenantBerbeda') === true) {
                    throw PenulisanLintasTenant::Buat($model::class, $aktif, (int) $diberikan);
                }
            }
        });
    }
}
