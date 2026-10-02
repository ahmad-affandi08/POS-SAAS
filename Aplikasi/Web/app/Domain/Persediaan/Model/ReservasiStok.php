<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\StatusReservasiStok;
use Illuminate\Support\Carbon;

/**
 * Cadangan stok untuk pesanan yang belum ditagih (F-17 v3.48), dikelola hanya oleh `PencadangStok`. `Jumlah` dalam
 * satuan dasar produk berstok (bahan resep & komponen paket sudah diuraikan).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $JenisSumber
 * @property string $UuidSumber
 * @property int $IdProduk
 * @property int $IdGudang
 * @property string $Jumlah
 * @property StatusReservasiStok $Status
 * @property Carbon|null $DiselesaikanPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ReservasiStok extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ReservasiStok';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jumlah' => 'string',
            'Status' => StatusReservasiStok::class,
            'DiselesaikanPada' => 'datetime',
        ];
    }
}
