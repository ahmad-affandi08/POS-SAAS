<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Satuan jual/beli produk dengan konversi ke satuan dasar (PRD §15.3). `KonversiKeDasar` = string desimal 4 angka.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdSatuan
 * @property string $KonversiKeDasar
 * @property bool $DefaultJual
 * @property bool $DefaultBeli
 */
final class ProdukSatuan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukSatuan';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['KonversiKeDasar' => '1', 'DefaultJual' => false, 'DefaultBeli' => false];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['KonversiKeDasar' => 'decimal:4', 'DefaultJual' => 'boolean', 'DefaultBeli' => 'boolean'];
    }
}
