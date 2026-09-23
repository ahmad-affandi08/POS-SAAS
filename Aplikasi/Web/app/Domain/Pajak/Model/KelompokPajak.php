<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelompok pajak tenant (PRD §12.2, §15.3), misal "Makan & minum" = PBJT atas subtotal + service charge. Dipilih per
 * produk (`Produk.IdKelompokPajak`). Nama unik per tenant.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property-read Collection<int, KelompokPajakDetail> $Detail
 */
final class KelompokPajak extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KelompokPajak';

    /**
     * @return HasMany<KelompokPajakDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(KelompokPajakDetail::class, 'IdKelompokPajak', 'Id')->orderBy('Urutan');
    }
}
