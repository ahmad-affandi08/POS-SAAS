<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\CakupanTargetPenjualan;
use Illuminate\Support\Carbon;

/**
 * Target penjualan satu bulan `YYYY-MM` untuk satu outlet atau satu karyawan (F-18 bagian 3, EMP-05). Satu target per
 * sasaran per periode (`KunciSasaran`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Periode
 * @property CakupanTargetPenjualan $Cakupan
 * @property int|null $IdOutlet
 * @property int|null $IdKaryawan
 * @property string $KunciSasaran
 * @property string $Nilai
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class TargetPenjualan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TargetPenjualan';

    /** @var array<string, mixed> */
    protected $attributes = ['IdOutlet' => null, 'IdKaryawan' => null, 'DibuatOleh' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Cakupan' => CakupanTargetPenjualan::class];
    }
}
