<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\CaraPelunasanKasbon;
use Illuminate\Support\Carbon;

/**
 * Pelunasan kasbon (F-18 bagian 3): ke kas/bank (jurnal sendiri) atau potongan rekap gaji (ikut jurnal rekap gaji).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdKasbon
 * @property Carbon $Tanggal
 * @property string $Jumlah
 * @property CaraPelunasanKasbon $Cara
 * @property int|null $IdAkunKasBank
 * @property int|null $IdRekapGaji
 * @property string|null $Keterangan
 * @property int|null $IdJurnal
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 */
final class PelunasanKasbon extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PelunasanKasbon';

    /** @var array<string, mixed> */
    protected $attributes = ['IdAkunKasBank' => null, 'IdRekapGaji' => null, 'Keterangan' => null, 'IdJurnal' => null, 'DibuatOleh' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date', 'Cara' => CaraPelunasanKasbon::class];
    }
}
