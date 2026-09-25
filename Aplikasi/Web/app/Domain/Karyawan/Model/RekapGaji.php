<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\StatusRekapGaji;
use Illuminate\Support\Carbon;

/**
 * Rekap gaji satu periode `YYYY-MM` (F-18 bagian 3, EMP-06). Total = Σ baris; dihitung ulang setiap baris berubah.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Periode
 * @property StatusRekapGaji $Status
 * @property string $TotalKotor
 * @property string $TotalPotongan
 * @property string $TotalBersih
 * @property Carbon|null $TanggalBayar
 * @property int|null $IdAkunKasBank
 * @property int|null $IdAkunBeban
 * @property int|null $IdJurnal
 * @property int|null $DibuatOleh
 * @property int|null $DibayarOleh
 * @property Carbon|null $DibayarPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class RekapGaji extends ModelDasar
{
    use MilikTenant;

    protected $table = 'RekapGaji';

    /** @var array<string, mixed> */
    protected $attributes = [
        'TotalKotor' => '0.00',
        'TotalPotongan' => '0.00',
        'TotalBersih' => '0.00',
        'TanggalBayar' => null,
        'IdAkunKasBank' => null,
        'IdAkunBeban' => null,
        'IdJurnal' => null,
        'DibuatOleh' => null,
        'DibayarOleh' => null,
        'DibayarPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Status' => StatusRekapGaji::class, 'TanggalBayar' => 'date', 'DibayarPada' => 'datetime'];
    }
}
