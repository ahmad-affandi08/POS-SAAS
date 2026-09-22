<?php

declare(strict_types=1);

namespace Tests\Pendukung\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Model khusus test untuk tabel UjiCatatan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Judul
 */
final class UjiCatatan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'UjiCatatan';
}
