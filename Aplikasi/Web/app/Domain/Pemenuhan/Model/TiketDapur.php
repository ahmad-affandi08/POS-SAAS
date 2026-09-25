<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\StatusTiketDapur;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tiket dapur per (dokumen, ronde, stasiun) (F-10b fase 1). Dokumen = pesanan terbuka atau penjualan mode cepat.
 * Nomor dokumen, meja, dan label disalin saat dibuat agar layar dapur tidak bergantung pada dokumen asal.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdStasiunDapur
 * @property int|null $IdPesananTerbuka
 * @property int|null $IdPenjualan
 * @property string $NomorDokumen
 * @property string|null $NamaMeja
 * @property string|null $Label
 * @property int $Ronde
 * @property StatusTiketDapur $Status
 * @property Carbon $DikirimPada
 * @property Carbon|null $MulaiPada
 * @property Carbon|null $SiapPada
 * @property Carbon|null $DisajikanPada
 */
final class TiketDapur extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TiketDapur';

    /**
     * @return HasMany<TiketDapurDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(TiketDapurDetail::class, 'IdTiketDapur', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusTiketDapur::class,
            'Ronde' => 'integer',
            'DikirimPada' => 'datetime',
            'MulaiPada' => 'datetime',
            'SiapPada' => 'datetime',
            'DisajikanPada' => 'datetime',
        ];
    }
}
