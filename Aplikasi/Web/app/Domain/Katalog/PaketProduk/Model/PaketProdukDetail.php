<?php

declare(strict_types=1);

namespace App\Domain\Katalog\PaketProduk\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Komponen produk paket/bundel (PRD §15.3, F-03). `Jumlah` dalam satuan dasar komponen. `AlokasiHarga` = persen
 * porsi harga paket (H5); semua baris satu paket null (otomatis) atau jumlahnya tepat 100.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProdukPaket
 * @property int $IdProdukKomponen
 * @property string $Jumlah
 * @property string|null $AlokasiHarga
 * @property int $Urutan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Produk $ProdukKomponen
 */
final class PaketProdukDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PaketProdukDetail';

    /** @var array<string, mixed> */
    protected $attributes = ['AlokasiHarga' => null, 'Urutan' => 0];

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function ProdukKomponen(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProdukKomponen', 'Id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jumlah' => 'decimal:4', 'AlokasiHarga' => 'decimal:6', 'Urutan' => 'integer'];
    }
}
