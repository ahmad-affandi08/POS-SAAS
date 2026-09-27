<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Idempotensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Respons tersimpan untuk satu `Idempotency-Key` perangkat POS (audit F-12). Tanpa `Uuid` publik.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPerangkat
 * @property string $Kunci
 * @property string $Metode
 * @property string $Jalur
 * @property string $HashPermintaan
 * @property int $StatusHttp
 * @property string $Respons
 * @property Carbon $KedaluwarsaPada
 */
final class KunciIdempotensi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KunciIdempotensi';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'StatusHttp' => 'integer',
            'KedaluwarsaPada' => 'datetime',
        ];
    }
}
