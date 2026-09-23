<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Peran internal Platform Pengelola (PRD §15.3, §19.3).
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property bool $Bawaan
 */
final class PeranPengelola extends ModelDasar
{
    protected $table = 'PeranPengelola';

    protected bool $pakaiUuid = false;

    /**
     * @return HasMany<PeranPengelolaIzin, $this>
     */
    public function Izin(): HasMany
    {
        return $this->hasMany(PeranPengelolaIzin::class, 'IdPeranPengelola', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Bawaan' => 'boolean'];
    }
}
