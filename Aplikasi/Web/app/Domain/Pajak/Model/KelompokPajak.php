<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelompok pajak tenant (PRD §12.2, §15.3), misal "Makan & minum" = PBJT atas subtotal + service charge. Dipilih per
 * produk (`Produk.IdKelompokPajak`). Nama unik per tenant. `Kategori` (F-03) = kategori pajak produk; null hanya untuk
 * data lama sebelum diisi migrasi, `SimpanKelompokPajak` selalu mengisinya.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property KategoriPajakProduk|null $Kategori
 * @property-read Collection<int, KelompokPajakDetail> $Detail
 */
final class KelompokPajak extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KelompokPajak';

    /** @var array<string, mixed> */
    protected $attributes = ['Kategori' => null];

    /**
     * @return HasMany<KelompokPajakDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(KelompokPajakDetail::class, 'IdKelompokPajak', 'Id')->orderBy('Urutan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Kategori' => KategoriPajakProduk::class];
    }
}
