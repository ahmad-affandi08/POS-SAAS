<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produk jasa yang boleh ditukar dengan satu sesi paket (F-16d bagian 2).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPaketSesi
 * @property int $IdProduk
 * @property-read Produk $Produk
 */
final class PaketSesiProduk extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PaketSesiProduk';

    protected bool $pakaiUuid = false;

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function Produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProduk', 'Id');
    }
}
