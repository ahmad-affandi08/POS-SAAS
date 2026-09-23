<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barcode produk (PRD §15.3, F-03 BR-03.1): banyak per produk, masing-masing terikat satu `ProdukSatuan`, unik per
 * tenant (tanpa beda huruf besar/kecil). Dihapus permanen dengan jejak `PenghapusanKatalog`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdProdukSatuan
 * @property string $Barcode
 * @property-read Produk $Produk
 * @property-read ProdukSatuan $ProdukSatuan
 */
final class ProdukBarcode extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukBarcode';

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function Produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProduk', 'Id');
    }

    /**
     * @return BelongsTo<ProdukSatuan, $this>
     */
    public function ProdukSatuan(): BelongsTo
    {
        return $this->belongsTo(ProdukSatuan::class, 'IdProdukSatuan', 'Id');
    }
}
