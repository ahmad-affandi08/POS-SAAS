<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Promo\Enum\StatusPemakaianVoucher;
use Illuminate\Support\Carbon;

/**
 * Voucher untuk satu penjualan perangkat (F-16c bagian 2): pesanan online lalu pemakaian saat penjualan diterima.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdVoucher
 * @property string $UuidPenjualan
 * @property int|null $IdPenjualan
 * @property int|null $IdPerangkat
 * @property StatusPemakaianVoucher $Status
 * @property Carbon|null $DipesanSampai
 */
final class VoucherPemakaian extends ModelDasar
{
    use MilikTenant;

    protected $table = 'VoucherPemakaian';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Status' => StatusPemakaianVoucher::class, 'DipesanSampai' => 'datetime'];
    }
}
