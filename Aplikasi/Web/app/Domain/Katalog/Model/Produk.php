<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produk tenant (PRD §15.3). F-01 membuat produk awal (contoh template & tambah cepat); F-03 melengkapi SKU, varian,
 * merek, dan harga per daftar harga. `MetodeHpp`/`BolehMinus` null = ikut pengaturan tenant.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string|null $Sku
 * @property string $Nama
 * @property string|null $NamaStruk
 * @property JenisProduk $Jenis
 * @property int|null $IdKategori
 * @property int $IdSatuanDasar
 * @property PelacakanProduk $Pelacakan
 * @property int|null $IdKelompokPajak
 * @property string|null $MetodeHpp
 * @property bool|null $BolehMinus
 * @property bool $Aktif
 * @property bool $TampilDiPos
 * @property bool $TampilOnline
 * @property-read Kategori|null $Kategori
 * @property-read Collection<int, ProdukHarga> $Harga
 */
final class Produk extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Produk';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Sku' => null,
        'NamaStruk' => null,
        'IdKategori' => null,
        'Pelacakan' => 'Tidak',
        'IdKelompokPajak' => null,
        'MetodeHpp' => null,
        'BolehMinus' => null,
        'Aktif' => true,
        'TampilDiPos' => true,
        'TampilOnline' => false,
    ];

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function Kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'IdKategori', 'Id');
    }

    /**
     * @return HasMany<ProdukHarga, $this>
     */
    public function Harga(): HasMany
    {
        return $this->hasMany(ProdukHarga::class, 'IdProduk', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisProduk::class,
            'Pelacakan' => PelacakanProduk::class,
            'BolehMinus' => 'boolean',
            'Aktif' => 'boolean',
            'TampilDiPos' => 'boolean',
            'TampilOnline' => 'boolean',
        ];
    }
}
