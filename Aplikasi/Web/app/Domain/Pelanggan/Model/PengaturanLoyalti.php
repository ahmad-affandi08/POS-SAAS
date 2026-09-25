<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Pengaturan loyalti per tenant (F-16b). Tanpa baris = bawaan (nonaktif, Rp 10.000 = 1 poin, berlaku 12 bulan, tier
 * dievaluasi dari belanja 12 bulan; 1 poin = Rp 100 saat ditukar, minimal 10 poin sekali tukar).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $Aktif
 * @property string $BelanjaPerPoin
 * @property string $NilaiTukarPoin
 * @property int $MinimalTukarPoin
 * @property int $MasaBerlakuBulan
 * @property int $BulanEvaluasiTier
 */
final class PengaturanLoyalti extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanLoyalti';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => false, 'BelanjaPerPoin' => '10000.00', 'NilaiTukarPoin' => '100.00', 'MinimalTukarPoin' => 10, 'MasaBerlakuBulan' => 12, 'BulanEvaluasiTier' => 12];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'MinimalTukarPoin' => 'integer', 'MasaBerlakuBulan' => 'integer', 'BulanEvaluasiTier' => 'integer'];
    }
}
