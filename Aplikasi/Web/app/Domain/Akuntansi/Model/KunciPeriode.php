<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Periode akuntansi `YYYY-MM` yang dikunci (§11, DesainF05a B.2). Dibaca `PenjagaKunciPeriode`; UI penguncian di F-15.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $Periode
 * @property Carbon|null $DikunciPada
 * @property int|null $DikunciOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KunciPeriode extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KunciPeriode';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DikunciPada' => 'datetime'];
    }
}
