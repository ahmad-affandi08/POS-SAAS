<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Tenant yang dirujuk mitra (P-12, BR-P12.2: satu tenant satu mitra). Data platform: sengaja tanpa `MilikTenant`
 * (tidak terlihat tenant), seperti `OverrideTenant`. `Sumber` = `Tautan` (pendaftaran lewat tautan mitra).
 *
 * @property int $Id
 * @property int $IdMitra
 * @property int $IdTenant
 * @property string $Sumber
 * @property Carbon|null $DiklikPada
 * @property Carbon $MulaiPada
 * @property Carbon|null $BerakhirPada
 * @property Carbon|null $DibuatPada
 */
final class AtribusiMitra extends ModelDasar
{
    public const SUMBER_TAUTAN = 'Tautan';

    protected $table = 'AtribusiMitra';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['DiklikPada' => null, 'BerakhirPada' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DiklikPada' => 'datetime', 'MulaiPada' => 'datetime', 'BerakhirPada' => 'datetime'];
    }
}
