<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Pengaturan loyalti per tenant (F-16b). Tanpa baris = bawaan (nonaktif, Rp 10.000 = 1 poin, berlaku 12 bulan, tier
 * dievaluasi dari belanja 12 bulan).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $Aktif
 * @property string $BelanjaPerPoin
 * @property int $MasaBerlakuBulan
 * @property int $BulanEvaluasiTier
 */
final class PengaturanLoyalti extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanLoyalti';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => false, 'BelanjaPerPoin' => '10000.00', 'MasaBerlakuBulan' => 12, 'BulanEvaluasiTier' => 12];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'MasaBerlakuBulan' => 'integer', 'BulanEvaluasiTier' => 'integer'];
    }
}
