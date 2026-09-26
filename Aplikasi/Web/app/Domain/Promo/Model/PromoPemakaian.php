<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Pemakaian promo pada satu penjualan (F-16c): nilai potongan promo itu (baris + pesanan). v1.90: penjualan yang di-void
 * menandai `DibatalkanPada`; pemakaian yang dibatalkan tidak dihitung kuota, batas per pelanggan, maupun ringkasan.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPromo
 * @property int $IdPenjualan
 * @property int|null $IdPelanggan
 * @property Carbon $TanggalBisnis
 * @property string $JumlahDiskon
 * @property Carbon|null $DibatalkanPada
 */
final class PromoPemakaian extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PromoPemakaian';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['TanggalBisnis' => 'date', 'JumlahDiskon' => 'decimal:2', 'DibatalkanPada' => 'datetime'];
    }
}
