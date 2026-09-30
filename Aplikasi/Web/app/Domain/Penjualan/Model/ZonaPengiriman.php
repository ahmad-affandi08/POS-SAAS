<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property string $Nama
 * @property list<string> $KodePos
 * @property string $Ongkir
 * @property string|null $GratisMulai
 * @property int $EstimasiHariMin
 * @property int $EstimasiHariMaks
 * @property int $Urutan
 * @property bool $Aktif
 */
final class ZonaPengiriman extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ZonaPengiriman';

    protected $attributes = ['GratisMulai' => null, 'EstimasiHariMin' => 0, 'EstimasiHariMaks' => 0, 'Urutan' => 0, 'Aktif' => true];

    protected function casts(): array
    {
        return [
            'KodePos' => 'array',
            'Ongkir' => 'decimal:2',
            'GratisMulai' => 'decimal:2',
            'EstimasiHariMin' => 'integer',
            'EstimasiHariMaks' => 'integer',
            'Urutan' => 'integer',
            'Aktif' => 'boolean',
        ];
    }
}
