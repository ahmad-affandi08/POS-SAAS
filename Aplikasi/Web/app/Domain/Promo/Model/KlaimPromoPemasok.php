<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use Illuminate\Support\Carbon;

/**
 * Klaim promo ke pemasok untuk satu penjualan (F-16c bagian 4b): `Jumlah` = potongan promo × `PersenDana` (snapshot saat
 * penjualan diterima).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPromo
 * @property int $IdPemasok
 * @property int $IdPenjualan
 * @property Carbon $TanggalBisnis
 * @property string $JumlahDiskon
 * @property string $PersenDana
 * @property string $Jumlah
 * @property StatusKlaimPromo $Status
 * @property int|null $IdPenerimaanKlaimPemasok
 */
final class KlaimPromoPemasok extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KlaimPromoPemasok';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'JumlahDiskon' => 'decimal:2',
            'PersenDana' => 'decimal:2',
            'Jumlah' => 'decimal:2',
            'Status' => StatusKlaimPromo::class,
        ];
    }
}
