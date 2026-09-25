<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Absensi masuk/keluar dari aplikasi kasir (F-18, EMP-03). `Uuid` dibuat perangkat; swafoto di disk privat
 * (`config('karyawan.DiskSwafoto')`), hanya diunduh lewat rute berizin `karyawan.lihat`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdKaryawan
 * @property int $IdOutlet
 * @property int|null $IdPerangkat
 * @property Carbon $TanggalBisnis
 * @property Carbon $MasukPada
 * @property Carbon|null $KeluarPada
 * @property string|null $PathSwafotoMasuk
 * @property string|null $PathSwafotoKeluar
 */
final class Absensi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Absensi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'MasukPada' => 'datetime',
            'KeluarPada' => 'datetime',
        ];
    }
}
