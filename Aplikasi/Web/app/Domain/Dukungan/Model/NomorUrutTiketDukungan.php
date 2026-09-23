<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Penghitung nomor tiket per tahun untuk seluruh platform (bukan data tenant). Dibaca dengan kunci baris.
 *
 * @property int $Id
 * @property int $Tahun
 * @property int $NomorTerakhir
 */
final class NomorUrutTiketDukungan extends ModelDasar
{
    protected $table = 'NomorUrutTiketDukungan';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tahun' => 'integer', 'NomorTerakhir' => 'integer'];
    }
}
