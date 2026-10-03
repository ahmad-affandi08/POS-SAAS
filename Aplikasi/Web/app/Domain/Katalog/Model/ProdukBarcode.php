<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Layanan\PemberitahuProdukDiubah;
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

    protected static function booted(): void
    {
        // X7 §16.4 (v3.96): perubahan ProdukBarcode ikut mengubah data `GET /api/v1/produk` → webhook `produk.diubah`.
        $tandai = static function (ProdukBarcode $baris): void {
            PemberitahuProdukDiubah::TandaiDariModel($baris->IdTenant, $baris->IdProduk);
        };
        self::saved(static function (ProdukBarcode $baris) use ($tandai): void {
            if ($baris->wasRecentlyCreated || $baris->wasChanged()) {
                $tandai($baris);
            }
        });
        self::deleted($tandai);
    }

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
