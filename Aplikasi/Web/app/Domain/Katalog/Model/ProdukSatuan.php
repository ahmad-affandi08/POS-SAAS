<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satuan jual/beli produk dengan konversi ke satuan dasar (PRD §15.3). `KonversiKeDasar` = string desimal 4 angka.
 * `Uuid` (F-03) = ID publik untuk form produk, harga per satuan, dan katalog POS.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdSatuan
 * @property string $KonversiKeDasar
 * @property bool $DefaultJual
 * @property bool $DefaultBeli
 * @property-read Produk $Produk
 * @property-read Satuan $SatuanUnit
 * @property-read Collection<int, ProdukBarcode> $Barcode
 */
final class ProdukSatuan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukSatuan';

    /** @var array<string, mixed> */
    protected $attributes = ['KonversiKeDasar' => '1', 'DefaultJual' => false, 'DefaultBeli' => false];

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function Produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProduk', 'Id');
    }

    /**
     * Satuan tenant (`Satuan`) dari baris ini. Bukan `Satuan()` agar tidak rancu dengan relasi `Produk::Satuan()`.
     *
     * @return BelongsTo<Satuan, $this>
     */
    public function SatuanUnit(): BelongsTo
    {
        return $this->belongsTo(Satuan::class, 'IdSatuan', 'Id');
    }

    /**
     * @return HasMany<ProdukBarcode, $this>
     */
    public function Barcode(): HasMany
    {
        return $this->hasMany(ProdukBarcode::class, 'IdProdukSatuan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['KonversiKeDasar' => 'decimal:4', 'DefaultJual' => 'boolean', 'DefaultBeli' => 'boolean'];
    }
}
