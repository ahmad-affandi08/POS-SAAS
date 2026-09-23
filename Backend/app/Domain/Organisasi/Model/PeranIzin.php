<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Izin yang dimiliki satu peran tenant (kunci enum `IzinTenant`).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPeran
 * @property string $KunciIzin
 */
final class PeranIzin extends ModelDasar
{
    use MilikTenant;

    public $timestamps = false;

    protected $table = 'PeranIzin';

    protected bool $pakaiUuid = false;
}
