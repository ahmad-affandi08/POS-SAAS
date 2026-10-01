<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * F-05i: penitip (pemasok) sebuah produk konsinyasi, ditetapkan oleh titipan masuk pertama. Satu produk satu penitip
 * supaya hutang hasil penjualannya jelas milik siapa (dasar setoran per penitip).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdPemasok
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PenitipProduk extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenitipProduk';

    protected bool $pakaiUuid = false;
}
