<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Detak terakhir scheduler (P-11, BR-P11.1). Satu baris per nama penjadwal.
 *
 * @property int $Id
 * @property string $Nama
 * @property Carbon $TerakhirPada
 */
final class DetakPenjadwal extends ModelDasar
{
    public const NAMA_UTAMA = 'Penjadwal';

    protected $table = 'DetakPenjadwal';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['TerakhirPada' => 'datetime'];
    }
}
