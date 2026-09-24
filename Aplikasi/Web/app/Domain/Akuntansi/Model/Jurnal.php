<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Header jurnal (§11.1, DesainF05a B.2/C.5). Hanya dibuat `PostingJurnal`/`BalikkanJurnal` dan tidak pernah diubah
 * atau dihapus (aturan #8): koreksi = jurnal pembalik (`IdJurnalDibalik`). Uang disimpan sebagai string desimal.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property Carbon $Tanggal
 * @property string $Periode
 * @property JenisSumberJurnal $JenisSumber
 * @property int $IdSumber
 * @property string|null $UuidSumber
 * @property string|null $NomorSumber
 * @property string $KunciSumber
 * @property string $Keterangan
 * @property bool $Otomatis
 * @property int|null $IdJurnalDibalik
 * @property string $TotalDebit
 * @property string $TotalKredit
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, JurnalDetail> $Detail
 * @property-read Jurnal|null $JurnalDibalik
 */
final class Jurnal extends ModelDasar
{
    use MilikTenant;

    public const PESAN_TIDAK_BISA_DIUBAH = 'Jurnal yang sudah diposting tidak bisa diubah/dihapus';

    protected $table = 'Jurnal';

    /** @var array<string, mixed> */
    protected $attributes = ['KunciSumber' => 'Utama', 'Otomatis' => true];

    /**
     * @return HasMany<JurnalDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(JurnalDetail::class, 'IdJurnal', 'Id');
    }

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function JurnalDibalik(): BelongsTo
    {
        return $this->belongsTo(self::class, 'IdJurnalDibalik', 'Id');
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException(self::PESAN_TIDAK_BISA_DIUBAH);
        });
        self::deleting(function (): void {
            throw new LogicException(self::PESAN_TIDAK_BISA_DIUBAH);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'JenisSumber' => JenisSumberJurnal::class,
            'IdSumber' => 'integer',
            'Otomatis' => 'boolean',
            'TotalDebit' => 'string',
            'TotalKredit' => 'string',
        ];
    }
}
