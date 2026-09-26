<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Definisi paket sesi (F-16d bagian 2, CRM-04): produk berjenis Jasa yang dijual sebagai N sesi. Produk yang boleh
 * ditukar: `Produk` di `PaketSesiProduk`, atau semua produk Jasa bila `SemuaProdukJasa`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $JumlahSesi
 * @property int|null $MasaBerlakuHari
 * @property bool $SemuaProdukJasa
 * @property bool $Aktif
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Produk $Produk
 * @property-read Collection<int, PaketSesiProduk> $ProdukBerlaku
 */
final class PaketSesi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PaketSesi';

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function Produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProduk', 'Id');
    }

    /**
     * @return HasMany<PaketSesiProduk, $this>
     */
    public function ProdukBerlaku(): HasMany
    {
        return $this->hasMany(PaketSesiProduk::class, 'IdPaketSesi', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JumlahSesi' => 'integer',
            'MasaBerlakuHari' => 'integer',
            'SemuaProdukJasa' => 'boolean',
            'Aktif' => 'boolean',
        ];
    }
}
