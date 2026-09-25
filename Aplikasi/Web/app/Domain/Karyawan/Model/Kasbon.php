<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\StatusKasbon;
use Illuminate\Support\Carbon;

/**
 * Kasbon karyawan (F-18 bagian 3, J-18.1). Dokumen append-only: tidak diubah; pembatalan membalik jurnal, pelunasan
 * lewat `PelunasanKasbon`. `Sisa` = Jumlah − Σ pelunasan, diperbarui di transaksi yang sama dengan pelunasan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdKaryawan
 * @property Carbon $Tanggal
 * @property string $Jumlah
 * @property string $Sisa
 * @property int $IdAkunKasBank
 * @property string|null $Keterangan
 * @property StatusKasbon $Status
 * @property int|null $IdJurnal
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class Kasbon extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Kasbon';

    /** @var array<string, mixed> */
    protected $attributes = ['Keterangan' => null, 'IdJurnal' => null, 'DibatalkanPada' => null, 'AlasanBatal' => null, 'DibuatOleh' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date', 'Status' => StatusKasbon::class, 'DibatalkanPada' => 'datetime'];
    }
}
