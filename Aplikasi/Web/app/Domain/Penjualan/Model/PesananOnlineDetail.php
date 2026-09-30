<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPesananOnline
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $UuidProduk
 * @property int|null $IdProdukSatuan
 * @property string $UuidProdukSatuan
 * @property string $NamaProduk
 * @property string $Jumlah
 * @property string $HargaSatuan
 * @property string $HargaPilihan
 * @property list<array<string, mixed>>|null $Pilihan
 * @property string|null $Catatan
 * @property array<string, mixed>|null $SnapshotPajak
 * @property string $TotalBaris
 */
final class PesananOnlineDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananOnlineDetail';

    protected $attributes = ['IdProdukSatuan' => null, 'Pilihan' => null, 'Catatan' => null, 'SnapshotPajak' => null];

    protected function casts(): array
    {
        return [
            'Jumlah' => 'decimal:4', 'HargaSatuan' => 'decimal:2', 'HargaPilihan' => 'decimal:2',
            'Pilihan' => 'array', 'SnapshotPajak' => 'array', 'TotalBaris' => 'decimal:2',
        ];
    }
}
