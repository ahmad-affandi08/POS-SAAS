<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\StatusPaket;

/**
 * Add-on langganan (P-04): fitur dan/atau tambahan batas yang dibeli terpisah. `TambahanBatas` memakai nama kolom
 * batas paket, misal {"BatasOutlet": 1}. Status memakai Aktif/Diarsipkan (Draf tidak dipakai untuk add-on).
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property string $HargaBulanan
 * @property string|null $KunciFitur
 * @property array<string, int>|null $TambahanBatas
 * @property StatusPaket $Status
 */
final class Addon extends ModelDasar
{
    protected $table = 'Addon';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['KunciFitur' => null, 'TambahanBatas' => null, 'Status' => 'Aktif'];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['HargaBulanan' => 'decimal:2', 'TambahanBatas' => 'array', 'Status' => StatusPaket::class];
    }
}
