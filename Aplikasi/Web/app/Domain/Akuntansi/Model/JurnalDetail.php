<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris jurnal (§11.1, §15.4, DesainF05a B.2). Tepat satu sisi > 0; `Tanggal` didenormalisasi dari header. Tidak
 * pernah diubah atau dihapus.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdJurnal
 * @property int $Urutan
 * @property int $IdAkun
 * @property int|null $IdOutlet
 * @property string $Debit
 * @property string $Kredit
 * @property string|null $Memo
 * @property Carbon $Tanggal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Jurnal $Jurnal
 * @property-read Akun $Akun
 */
final class JurnalDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'JurnalDetail';

    protected bool $pakaiUuid = false;

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function Jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'IdJurnal', 'Id');
    }

    /**
     * @return BelongsTo<Akun, $this>
     */
    public function Akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'IdAkun', 'Id');
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException(Jurnal::PESAN_TIDAK_BISA_DIUBAH);
        });
        self::deleting(function (): void {
            throw new LogicException(Jurnal::PESAN_TIDAK_BISA_DIUBAH);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Urutan' => 'integer',
            'Debit' => 'string',
            'Kredit' => 'string',
            'Tanggal' => 'date',
        ];
    }
}
