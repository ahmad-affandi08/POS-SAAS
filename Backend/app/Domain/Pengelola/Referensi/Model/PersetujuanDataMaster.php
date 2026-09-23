<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Keputusan peninjau atas data master (four-eyes, BR-P02.2, PRD §15.3). Append-only.
 * Hanya keputusan setelah `DiajukanPada` terakhir data tersebut yang dihitung.
 *
 * @property int $Id
 * @property string $JenisData
 * @property int $IdData
 * @property int $IdPenggunaPengelola
 * @property KeputusanTinjauan $Keputusan
 * @property string|null $Catatan
 * @property Carbon $DibuatPada
 * @property-read PenggunaPengelola $Peninjau
 */
final class PersetujuanDataMaster extends ModelDasar
{
    public const UPDATED_AT = null;

    protected $table = 'PersetujuanDataMaster';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('PersetujuanDataMaster bersifat append-only (BR-P02.2).');
        });

        self::deleting(static function (): never {
            throw new LogicException('PersetujuanDataMaster bersifat append-only (BR-P02.2).');
        });
    }

    /**
     * @return BelongsTo<PenggunaPengelola, $this>
     */
    public function Peninjau(): BelongsTo
    {
        return $this->belongsTo(PenggunaPengelola::class, 'IdPenggunaPengelola', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Keputusan' => KeputusanTinjauan::class, 'DibuatPada' => 'datetime'];
    }
}
