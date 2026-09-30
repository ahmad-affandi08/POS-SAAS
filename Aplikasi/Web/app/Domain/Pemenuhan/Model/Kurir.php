<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\JenisKurir;
use App\Domain\Pemenuhan\Enum\StatusKurir;

/**
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property string|null $NoHp
 * @property JenisKurir $Jenis
 * @property string|null $NamaPenyedia
 * @property StatusKurir $Status
 */
final class Kurir extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Kurir';

    protected $hidden = ['NoHp'];

    protected $attributes = ['NoHp' => null, 'NamaPenyedia' => null, 'Status' => 'Aktif'];

    protected function casts(): array
    {
        return ['NoHp' => 'encrypted', 'Jenis' => JenisKurir::class, 'Status' => StatusKurir::class];
    }
}
