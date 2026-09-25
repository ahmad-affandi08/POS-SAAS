<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;

/**
 * Pengaturan promo per tenant (F-16c). Tanpa baris = mode `Terbaik`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property ModeResolusiPromo $ModeResolusi
 */
final class PengaturanPromo extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanPromo';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['ModeResolusi' => 'Terbaik'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ModeResolusi' => ModeResolusiPromo::class];
    }
}
