<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Log audit Platform Pengelola, **append-only** (BR-P01.3, PRD §15.3). Baris tidak pernah diubah atau dihapus.
 * Tulis hanya lewat `PencatatAuditPengelola`.
 *
 * @property int $Id
 * @property int|null $IdPenggunaPengelola
 * @property string $Aksi
 * @property string|null $JenisObjek
 * @property int|null $IdObjek
 * @property int|null $IdTenant
 * @property array<string, mixed>|null $NilaiLama
 * @property array<string, mixed>|null $NilaiBaru
 * @property string|null $Alasan
 * @property string|null $Ip
 * @property Carbon $DibuatPada
 * @property-read PenggunaPengelola|null $Pelaku
 */
final class LogAuditPengelola extends ModelDasar
{
    public const UPDATED_AT = null;

    protected $table = 'LogAuditPengelola';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('LogAuditPengelola bersifat append-only dan tidak boleh diubah (BR-P01.3).');
        });

        self::deleting(static function (): never {
            throw new LogicException('LogAuditPengelola bersifat append-only dan tidak boleh dihapus (BR-P01.3).');
        });
    }

    /**
     * @return BelongsTo<PenggunaPengelola, $this>
     */
    public function Pelaku(): BelongsTo
    {
        return $this->belongsTo(PenggunaPengelola::class, 'IdPenggunaPengelola', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'NilaiLama' => 'array',
            'NilaiBaru' => 'array',
            'DibuatPada' => 'datetime',
        ];
    }
}
