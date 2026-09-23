<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Enum\JenisNomorUrutKatalog;

/**
 * Penghitung nomor urut katalog per tenant (F-03 BR-03.1): SKU otomatis dan barcode internal. Dikunci
 * `FOR UPDATE` lalu dinaikkan di transaksi yang sama dengan penyimpanan produk.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property JenisNomorUrutKatalog $Jenis
 * @property int $NomorTerakhir
 */
final class NomorUrutKatalog extends ModelDasar
{
    use MilikTenant;

    protected $table = 'NomorUrutKatalog';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['NomorTerakhir' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => JenisNomorUrutKatalog::class, 'NomorTerakhir' => 'integer'];
    }
}
