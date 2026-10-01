<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Satu unggahan rekening koran (FIN-09).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdAkun
 * @property string $NamaBerkas
 * @property int $JumlahBaris
 * @property int $Baru
 * @property int $Duplikat
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ImporMutasiBank extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ImporMutasiBank';
}
