<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisKompatibilitas;
use App\Domain\Tenant\Enum\StatusKompatibilitas;
use Illuminate\Support\Carbon;

/**
 * Satu baris Hardware Compatibility List (PRD §17.2.5a, v1.98). Data platform (tanpa `MilikTenant`): angka disegarkan
 * Platform Pengelola dari `Perangkat.ProfilHardware` lintas tenant, tanpa nama tenant. Status efektif = `StatusManual`
 * bila ada, selain itu `StatusOtomatis`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property JenisKompatibilitas $Jenis
 * @property string $Kunci
 * @property string $Nama
 * @property string|null $Sambungan
 * @property int $JumlahPerangkat
 * @property int $JumlahTenant
 * @property int $JumlahLolos
 * @property int $JumlahGagal
 * @property StatusKompatibilitas $StatusOtomatis
 * @property StatusKompatibilitas|null $StatusManual
 * @property string|null $Catatan
 * @property Carbon|null $TerakhirDiujiPada
 * @property Carbon|null $DisegarkanPada
 * @property int|null $DiubahOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KompatibilitasPerangkat extends ModelDasar
{
    protected $table = 'KompatibilitasPerangkat';

    protected $attributes = [
        'JumlahPerangkat' => 0,
        'JumlahTenant' => 0,
        'JumlahLolos' => 0,
        'JumlahGagal' => 0,
        'StatusManual' => null,
        'Catatan' => null,
        'Sambungan' => null,
    ];

    public function AmbilStatus(): StatusKompatibilitas
    {
        return $this->StatusManual ?? $this->StatusOtomatis;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisKompatibilitas::class,
            'StatusOtomatis' => StatusKompatibilitas::class,
            'StatusManual' => StatusKompatibilitas::class,
            'JumlahPerangkat' => 'integer',
            'JumlahTenant' => 'integer',
            'JumlahLolos' => 'integer',
            'JumlahGagal' => 'integer',
            'TerakhirDiujiPada' => 'datetime',
            'DisegarkanPada' => 'datetime',
        ];
    }
}
