<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Model dasar untuk semua tabel proyek (PRD §13.7.2, §15.1).
 *
 * - Primary key `Id`, kolom waktu `DibuatPada` / `DiubahPada` / `DihapusPada`.
 * - Foreign key ditebak sebagai `Id{NamaModel}`, misal `IdPenjualan`.
 * - Kolom `Uuid` (ULID) diisi otomatis dan dipakai sebagai ID publik di URL.
 */
abstract class ModelDasar extends Model
{
    public const CREATED_AT = 'DibuatPada';

    public const UPDATED_AT = 'DiubahPada';

    public const DELETED_AT = 'DihapusPada';

    protected $primaryKey = 'Id';

    /** @var list<string> */
    protected $guarded = ['Id'];

    /** Tabel tanpa kolom `Uuid` menimpa nilai ini menjadi false. */
    protected bool $pakaiUuid = true;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ModelDasar $model): void {
            if ($model->pakaiUuid && blank($model->getAttribute('Uuid'))) {
                $model->setAttribute('Uuid', (string) Str::ulid());
            }
        });
    }

    public function getForeignKey(): string
    {
        return 'Id'.class_basename($this);
    }

    public function getRouteKeyName(): string
    {
        return $this->pakaiUuid ? 'Uuid' : $this->getKeyName();
    }
}
