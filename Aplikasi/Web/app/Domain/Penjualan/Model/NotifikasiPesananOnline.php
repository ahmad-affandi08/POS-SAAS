<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\PeristiwaPesananOnline;
use Illuminate\Support\Carbon;

/**
 * Pemberitahuan WhatsApp status pesanan online ke pembeli (F-17 bagian 3, v3.32): satu baris per pesanan per peristiwa.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPesananOnline
 * @property PeristiwaPesananOnline $Peristiwa
 * @property int $Percobaan
 * @property Carbon|null $TerkirimPada
 * @property string|null $Galat
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class NotifikasiPesananOnline extends ModelDasar
{
    use MilikTenant;

    protected $table = 'NotifikasiPesananOnline';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Percobaan' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Peristiwa' => PeristiwaPesananOnline::class, 'Percobaan' => 'integer', 'TerkirimPada' => 'datetime'];
    }
}
