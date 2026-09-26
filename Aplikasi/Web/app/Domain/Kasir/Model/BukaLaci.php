<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Log buka laci kas manual tanpa transaksi (cetak struk bagian 4, POS-17, §19.2). Append-only: tidak diubah atau
 * dihapus setelah diterima.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdShift
 * @property int $IdPerangkat
 * @property string $Alasan
 * @property int $DibukaOleh
 * @property int|null $DisetujuiOleh
 * @property Carbon $DibukaPada
 * @property Carbon $DiterimaPada
 * @property bool $PerluTinjauan
 * @property string|null $AlasanTinjauan
 */
final class BukaLaci extends ModelDasar
{
    use MilikTenant;

    protected $table = 'BukaLaci';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'DibukaPada' => 'datetime',
            'DiterimaPada' => 'datetime',
            'PerluTinjauan' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Log buka laci append-only: tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Log buka laci tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function Shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'IdShift', 'Id');
    }
}
