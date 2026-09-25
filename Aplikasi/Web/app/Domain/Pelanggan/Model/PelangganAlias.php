<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Uuid pelanggan dari perangkat yang dipetakan ke pelanggan yang sudah ada (nomor HP sama, dibuat offline; F-16a).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $Uuid
 * @property int $IdPelanggan
 */
final class PelangganAlias extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PelangganAlias';
}
