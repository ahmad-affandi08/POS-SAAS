<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Gambar unggahan konsol untuk situs pemasaran (D-21), disimpan di disk `config('situs.Disk')`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Path
 * @property string $NamaBerkas
 * @property string $TipeMime
 * @property int $Ukuran
 * @property int|null $Lebar
 * @property int|null $Tinggi
 * @property string|null $TeksAlternatif
 * @property int|null $IdPenggunaPengelolaPengunggah
 * @property Carbon|null $DibuatPada
 */
final class GambarSitus extends ModelDasar
{
    protected $table = 'GambarSitus';

    public function getRouteKeyName(): string
    {
        return 'Uuid';
    }
}
