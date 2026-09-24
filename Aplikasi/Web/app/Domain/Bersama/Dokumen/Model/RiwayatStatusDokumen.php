<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Riwayat perubahan status dokumen transaksi (§13.3, DesainF05a B.2). Append-only: ditulis lewat
 * `PencatatRiwayatStatus`, tidak pernah diubah atau dihapus. Hanya kolom waktu `DiubahPada`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $JenisDokumen
 * @property int $IdDokumen
 * @property string|null $StatusDari
 * @property string $StatusKe
 * @property string|null $Alasan
 * @property int|null $DiubahOleh
 * @property Carbon|null $DiubahPada
 */
final class RiwayatStatusDokumen extends ModelDasar
{
    use MilikTenant;

    public const CREATED_AT = null;

    protected $table = 'RiwayatStatusDokumen';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Riwayat status dokumen tidak bisa diubah/dihapus');
        });
        self::deleting(function (): void {
            throw new LogicException('Riwayat status dokumen tidak bisa diubah/dihapus');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['IdDokumen' => 'integer', 'DiubahPada' => 'datetime'];
    }
}
