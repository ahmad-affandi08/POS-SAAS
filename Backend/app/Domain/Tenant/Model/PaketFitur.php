<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * @property int $Id
 * @property int $IdPaket
 * @property string $KunciFitur
 */
final class PaketFitur extends ModelDasar
{
    public $timestamps = false;

    protected $table = 'PaketFitur';

    protected bool $pakaiUuid = false;
}
