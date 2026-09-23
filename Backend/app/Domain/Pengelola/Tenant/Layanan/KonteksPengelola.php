<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Layanan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Bersama\Tenant\LingkupTenant;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya jalan Platform Pengelola membaca data milik tenant (model `MilikTenant`), CLAUDE.md #11, PRD §13.4,
 * §13.8. Setiap pemanggilan wajib beralasan dan dicatat di `LogAuditPengelola` (aksi `tenant.data.akses`).
 *
 * - Dengan `$idTenant`: tenant aktif sementara ditetapkan ke tenant itu, sehingga scope `MilikTenant` tetap bekerja dan
 *   hanya data tenant tersebut yang terbaca. Konteks sebelumnya dipulihkan setelah selesai (juga saat galat).
 * - Tanpa `$idTenant` (lintas semua tenant, misal agregat platform): closure memakai `KueriLintas()` yang melepas
 *   `LingkupTenant`. `KueriLintas()` menolak dipanggil di luar `JalankanLintasTenant`.
 */
final class KonteksPengelola
{
    public const AKSI_AUDIT = 'tenant.data.akses';

    private int $kedalamanLintas = 0;

    public function __construct(
        private readonly KonteksTenant $konteksTenant,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /**
     * @template THasil
     *
     * @param  Closure(self): THasil  $fungsi
     * @return THasil
     */
    public function JalankanLintasTenant(string $alasan, Closure $fungsi, ?int $idTenant = null): mixed
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new InvalidArgumentException('Akses data tenant oleh pengelola wajib menyebut alasan.');
        }

        $this->audit->Catat(
            self::AKSI_AUDIT,
            nilaiBaru: ['Cakupan' => $idTenant === null ? 'SemuaTenant' : 'SatuTenant'],
            alasan: $alasan,
            idTenant: $idTenant,
        );

        if ($idTenant === null) {
            $this->kedalamanLintas++;

            try {
                return $fungsi($this);
            } finally {
                $this->kedalamanLintas--;
            }
        }

        $konteksSebelumnya = $this->konteksTenant->Ambil();
        $this->konteksTenant->Atur($idTenant);

        try {
            return $fungsi($this);
        } finally {
            $konteksSebelumnya === null ? $this->konteksTenant->Kosongkan() : $this->konteksTenant->Atur($konteksSebelumnya);
        }
    }

    /**
     * Kueri model `MilikTenant` tanpa scope tenant. Hanya di dalam `JalankanLintasTenant` tanpa `$idTenant`.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $kelasModel
     * @return Builder<TModel>
     */
    public function KueriLintas(string $kelasModel): Builder
    {
        if ($this->kedalamanLintas === 0) {
            throw new LogicException('KueriLintas hanya boleh dipanggil di dalam KonteksPengelola::JalankanLintasTenant tanpa tenant.');
        }

        return $kelasModel::query()->withoutGlobalScope(LingkupTenant::class);
    }
}
