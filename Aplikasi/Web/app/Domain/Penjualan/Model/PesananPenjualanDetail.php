<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Baris pesanan penjualan / pre-order (F-12 bagian 2): barang, jumlah, dan harga saat dipesan (dipakai sebagai harga
 * penawaran saat diambil). `Uuid` dibuat di perangkat.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPesananPenjualan
 * @property int $IdProduk
 * @property string $UuidProduk
 * @property string|null $UuidProdukSatuan
 * @property string $NamaProduk
 * @property string $Jumlah
 * @property string $HargaSatuan
 * @property string $HargaPilihan
 * @property list<array{UuidPilihan: string, Nama: string, Harga: string}>|null $Pilihan
 * @property string|null $Catatan
 */
final class PesananPenjualanDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananPenjualanDetail';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jumlah' => 'decimal:4',
            'HargaSatuan' => 'decimal:2',
            'HargaPilihan' => 'decimal:2',
            'Pilihan' => 'array',
        ];
    }
}
