<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Merek usaha dalam satu tenant (PRD §15.3). F-00 membuat satu merek bawaan bernama sama dengan usaha.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 */
final class Merek extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Merek';
}
