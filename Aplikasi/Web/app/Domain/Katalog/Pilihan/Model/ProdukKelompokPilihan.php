<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kelompok pilihan yang dipasang ke produk, berurutan (PRD §15.3, F-03). Anak varian mewarisi kelompok induknya.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdKelompokPilihan
 * @property int $Urutan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read KelompokPilihan $KelompokPilihan
 */
final class ProdukKelompokPilihan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukKelompokPilihan';

    /** @var array<string, mixed> */
    protected $attributes = ['Urutan' => 0];

    /**
     * @return BelongsTo<KelompokPilihan, $this>
     */
    public function KelompokPilihan(): BelongsTo
    {
        return $this->belongsTo(KelompokPilihan::class, 'IdKelompokPilihan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Urutan' => 'integer'];
    }
}
