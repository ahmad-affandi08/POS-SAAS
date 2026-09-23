<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pajak\Enum\CakupanPajak;

/**
 * Jenis pajak (PRD §12.1, §15.3), misal Ppn (nasional) dan PbjtMakananMinuman (daerah).
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property CakupanPajak $Cakupan
 */
final class JenisPajak extends ModelDasar
{
    protected $table = 'JenisPajak';

    protected bool $pakaiUuid = false;

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Cakupan' => CakupanPajak::class];
    }
}
