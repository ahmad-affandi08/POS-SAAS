<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Bersama;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Bersama\Tenant\LingkupTenant;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya jalan Platform Pengelola membaca/mengubah data tenant (PRD §13.4, §13.8, CLAUDE.md #11).
 * Setiap pemanggilan mencatat `LogAuditPengelola` (aksi `lintas-tenant.akses`, alasan, tenant).
 *
 * - Dengan `$idTenant`: tenant aktif sementara diganti ke tenant itu, sehingga model `MilikTenant` tetap
 *   terbatas ke satu tenant dan data baru otomatis ber-`IdTenant` benar. Tenant aktif sebelumnya dipulihkan.
 * - Tanpa `$idTenant` (misal antrean tiket semua tenant): kueri dibuat lewat `KueriLintasTenant()`, yang hanya sah
 *   di dalam callback ini.
 *
 * Versi minimal P-09 (tim C); disatukan dengan versi tim A oleh lead bila berbeda.
 */
final class KonteksPengelola
{
    private int $kedalaman = 0;

    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly KonteksTenant $konteksTenant,
    ) {}

    /**
     * @template THasil
     *
     * @param  callable(): THasil  $fn
     * @return THasil
     */
    public function JalankanLintasTenant(string $alasan, callable $fn, ?int $idTenant = null): mixed
    {
        if (trim($alasan) === '') {
            throw new InvalidArgumentException('Akses lintas tenant wajib menyebut alasan.');
        }

        $this->audit->Catat('lintas-tenant.akses', alasan: $alasan, idTenant: $idTenant);

        $tenantSebelumnya = $this->konteksTenant->Ambil();

        if ($idTenant !== null) {
            $this->konteksTenant->Atur($idTenant);
        }

        $this->kedalaman++;

        try {
            return $fn();
        } finally {
            $this->kedalaman--;

            if ($tenantSebelumnya === null) {
                $this->konteksTenant->Kosongkan();
            } else {
                $this->konteksTenant->Atur($tenantSebelumnya);
            }
        }
    }

    /**
     * Kueri model tenant tanpa batas tenant. Hanya boleh dipanggil di dalam `JalankanLintasTenant()`.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $kelasModel
     * @return Builder<TModel>
     */
    public function KueriLintasTenant(string $kelasModel): Builder
    {
        if ($this->kedalaman === 0) {
            throw new LogicException('Kueri lintas tenant hanya boleh di dalam KonteksPengelola::JalankanLintasTenant().');
        }

        return $kelasModel::query()->withoutGlobalScope(LingkupTenant::class);
    }
}
