<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Stok minimum/maksimum produk per lokasi stok (F-03). Jumlah string desimal 4 angka dalam satuan dasar; null =
 * tidak diatur. Hanya untuk jenis produk yang punya stok. `IdGudang` merujuk lokasi stok domain Organisasi.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdGudang
 * @property string|null $StokMinimum
 * @property string|null $StokMaksimum
 */
final class ProdukGudang extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukGudang';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['StokMinimum' => null, 'StokMaksimum' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['StokMinimum' => 'decimal:4', 'StokMaksimum' => 'decimal:4'];
    }
}
