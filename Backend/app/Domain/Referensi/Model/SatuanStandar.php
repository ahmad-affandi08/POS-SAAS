<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Satuan standar platform (pcs, kg, liter, ...), disalin ke `Satuan` tenant oleh template sektor (P-02, PRD §15.3).
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property string $Simbol
 * @property bool $BolehDesimal
 * @property bool $Aktif
 */
final class SatuanStandar extends ModelDasar
{
    protected $table = 'SatuanStandar';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['BolehDesimal' => false, 'Aktif' => true];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['BolehDesimal' => 'boolean', 'Aktif' => 'boolean'];
    }
}
