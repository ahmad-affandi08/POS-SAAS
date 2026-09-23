<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Katalog fitur (P-04). `Kunci` unik berformat D-06, misal `pos.mode-meja`, `api.publik`.
 *
 * @property int $Id
 * @property string $Kunci
 * @property string $Nama
 * @property string $Modul
 * @property string|null $Keterangan
 */
final class Fitur extends ModelDasar
{
    protected $table = 'Fitur';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Keterangan' => null];

    public function getRouteKeyName(): string
    {
        return 'Kunci';
    }
}
