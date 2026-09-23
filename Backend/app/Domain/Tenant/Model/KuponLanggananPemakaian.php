<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Pemakaian kupon langganan pada satu tagihan (BR-P04.7, PRD §15.3). Sengaja tanpa `MilikTenant`: kuota kupon
 * dihitung lintas tenant (jumlah tenant berbeda), dan setiap kueri per tenant selalu menyaring `IdTenant` eksplisit.
 * Pemakaian dari tagihan yang dibatalkan ditandai `DibatalkanPada` sehingga tidak lagi dihitung.
 *
 * @property int $Id
 * @property int $IdKuponLangganan
 * @property int $IdTenant
 * @property int $IdTagihanLangganan
 * @property int $BulanDiskon
 * @property string $Diskon
 * @property Carbon|null $DibatalkanPada
 */
final class KuponLanggananPemakaian extends ModelDasar
{
    protected $table = 'KuponLanggananPemakaian';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['DibatalkanPada' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['BulanDiskon' => 'integer', 'Diskon' => 'decimal:2', 'DibatalkanPada' => 'datetime'];
    }
}
