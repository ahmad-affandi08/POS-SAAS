<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Void penjualan (PRD §15 `VoidPenjualan`, F-09 fase 1): pembatalan seluruh penjualan lunas di shift yang sama. `Uuid`
 * = Uuid item outbox `Penjualan.Void`. Append-only; hanya `IdJurnal` diisi sekali di transaksi penerimaan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPenjualan
 * @property int $IdOutlet
 * @property int $IdShift
 * @property int $IdPerangkat
 * @property string $Alasan
 * @property int $DivoidOleh
 * @property int $DisetujuiOleh
 * @property Carbon $DivoidPada
 * @property Carbon $TanggalBisnis
 * @property string $Nominal
 * @property string $RefundTunai
 * @property string $RefundNonTunai
 * @property int|null $IdJurnal
 * @property Carbon $DiterimaPada
 */
final class VoidPenjualan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'VoidPenjualan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'DivoidPada' => 'datetime',
            'TanggalBisnis' => 'date',
            'Nominal' => 'decimal:2',
            'RefundTunai' => 'decimal:2',
            'RefundNonTunai' => 'decimal:2',
            'DiterimaPada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (VoidPenjualan $void): void {
            foreach (array_keys($void->getDirty()) as $kolom) {
                if ($kolom !== self::UPDATED_AT && ! ($kolom === 'IdJurnal' && $void->getOriginal('IdJurnal') === null)) {
                    throw new LogicException("Void penjualan append-only: kolom {$kolom} tidak boleh diubah.");
                }
            }
        });

        self::deleting(function (): void {
            throw new LogicException('Void penjualan tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<Penjualan, $this>
     */
    public function Penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'IdPenjualan', 'Id');
    }
}
