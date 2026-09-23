<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Izin milik satu peran pengelola (PRD §15.3). `KunciIzin` = nilai enum IzinPengelola.
 *
 * @property int $Id
 * @property int $IdPeranPengelola
 * @property string $KunciIzin
 */
final class PeranPengelolaIzin extends ModelDasar
{
    public $timestamps = false;

    protected $table = 'PeranPengelolaIzin';

    protected bool $pakaiUuid = false;
}
