<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Satuan tenant (PRD §15.3), disalin dari `SatuanStandar` (P-02) oleh template sektor. `KodeStandar` (tambahan §15)
 * = kode satuan standar sumbernya; null untuk satuan buatan tenant (F-03).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string|null $KodeStandar
 * @property string $Nama
 * @property string $Simbol
 * @property bool $BolehDesimal
 */
final class Satuan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Satuan';

    /** @var array<string, mixed> */
    protected $attributes = ['KodeStandar' => null, 'BolehDesimal' => false];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['BolehDesimal' => 'boolean'];
    }
}
