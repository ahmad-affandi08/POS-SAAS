<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Catatan internal tim tentang satu tenant (P-07, PRD §15.3). Tidak pernah terlihat oleh tenant, sehingga tanpa
 * `MilikTenant`. Append-only: koreksi ditulis sebagai catatan baru.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Isi
 * @property int $DibuatOleh
 * @property Carbon $DibuatPada
 * @property-read PenggunaPengelola $Penulis
 */
final class CatatanTenant extends ModelDasar
{
    protected $table = 'CatatanTenant';

    protected static function booted(): void
    {
        self::updating(static fn () => throw new LogicException('Catatan tenant tidak boleh diubah. Tulis catatan baru.'));
        self::deleting(static fn () => throw new LogicException('Catatan tenant tidak boleh dihapus.'));
    }

    /**
     * @return BelongsTo<PenggunaPengelola, $this>
     */
    public function Penulis(): BelongsTo
    {
        return $this->belongsTo(PenggunaPengelola::class, 'DibuatOleh', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DibuatPada' => 'datetime'];
    }
}
