<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Kas masuk/keluar/setoran non-penjualan dalam shift (PRD F-06 langkah 4, §15.3). Append-only: tidak diubah atau
 * dihapus setelah diterima, kecuali mengisi `IdJurnal` sekali dan `PathLampiran` sekali (unggahan bukti menyusul).
 * F-11: kas yang tiba setelah shift ditutup diterima dengan `PerluTinjauan` (`ShiftSudahDitutup`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdShift
 * @property JenisMutasiKas $Jenis
 * @property int|null $IdKategoriKas
 * @property string $Jumlah
 * @property string|null $Catatan
 * @property string|null $PathLampiran
 * @property int $DicatatOleh
 * @property Carbon $DicatatPada
 * @property Carbon $TanggalBisnis
 * @property int|null $DisetujuiOleh
 * @property int|null $IdJurnal
 * @property Carbon $DiterimaPada
 * @property bool $PerluTinjauan
 * @property string|null $AlasanTinjauan
 */
final class MutasiKas extends ModelDasar
{
    use MilikTenant;

    protected $table = 'MutasiKas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisMutasiKas::class,
            'Jumlah' => 'decimal:2',
            'DicatatPada' => 'datetime',
            'TanggalBisnis' => 'date',
            'DiterimaPada' => 'datetime',
            'PerluTinjauan' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (MutasiKas $mutasi): void {
            foreach (array_keys($mutasi->getDirty()) as $kolom) {
                $bolehSekali = in_array($kolom, ['IdJurnal', 'PathLampiran'], true) && $mutasi->getOriginal($kolom) === null;

                if (! $bolehSekali && $kolom !== self::UPDATED_AT) {
                    throw new LogicException("Mutasi kas append-only: kolom {$kolom} tidak boleh diubah.");
                }
            }
        });

        self::deleting(function (): void {
            throw new LogicException('Mutasi kas tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function Shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'IdShift', 'Id');
    }

    /**
     * @return BelongsTo<KategoriKas, $this>
     */
    public function KategoriKas(): BelongsTo
    {
        return $this->belongsTo(KategoriKas::class, 'IdKategoriKas', 'Id');
    }
}
