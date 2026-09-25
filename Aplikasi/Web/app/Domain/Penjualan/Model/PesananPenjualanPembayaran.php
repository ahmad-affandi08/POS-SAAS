<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;

/**
 * Pembayaran uang muka pesanan penjualan (F-12 bagian 2). `Uuid` dibuat di perangkat; tidak pernah diubah.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPesananPenjualan
 * @property int $IdMetodePembayaran
 * @property JenisMetodePembayaran $JenisMetode
 * @property string $Jumlah
 * @property string|null $Referensi
 */
final class PesananPenjualanPembayaran extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananPenjualanPembayaran';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['JenisMetode' => JenisMetodePembayaran::class, 'Jumlah' => 'decimal:2'];
    }
}
