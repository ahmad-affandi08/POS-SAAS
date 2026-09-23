<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kelompok pilihan (modifier) tenant (PRD §15.3, F-03), misal "Level Gula". "Wajib" = `MinimalPilih >= 1`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property int $MinimalPilih
 * @property int $MaksimalPilih
 * @property int $Urutan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, Pilihan> $Pilihan
 */
final class KelompokPilihan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KelompokPilihan';

    /** @var array<string, mixed> */
    protected $attributes = ['MinimalPilih' => 0, 'MaksimalPilih' => 1, 'Urutan' => 0];

    /**
     * @return HasMany<Pilihan, $this>
     */
    public function Pilihan(): HasMany
    {
        return $this->hasMany(Pilihan::class, 'IdKelompokPilihan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['MinimalPilih' => 'integer', 'MaksimalPilih' => 'integer', 'Urutan' => 'integer'];
    }
}
