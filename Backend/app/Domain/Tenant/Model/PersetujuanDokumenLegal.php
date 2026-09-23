<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Bukti persetujuan dokumen legal oleh pengguna tenant (P-06, BR-P06.5). Append-only.
 *
 * @property int $Id
 * @property int $IdDokumenLegal
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property Carbon $DisetujuiPada
 * @property string|null $Ip
 */
final class PersetujuanDokumenLegal extends ModelDasar
{
    public $timestamps = false;

    protected $table = 'PersetujuanDokumenLegal';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(static fn () => throw new LogicException('Persetujuan dokumen legal tidak boleh diubah.'));
        self::deleting(static fn () => throw new LogicException('Persetujuan dokumen legal tidak boleh dihapus.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DisetujuiPada' => 'datetime'];
    }
}
