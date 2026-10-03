<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\StatusWajahKaryawan;
use Illuminate\Support\Carbon;

/**
 * Wajah terdaftar karyawan untuk absensi web (F-18 bagian 4, D-37). Sidik wajah = deretan bilangan bulat (deskriptor
 * model wajah di browser × 10.000) per foto pendaftaran. Data pribadi spesifik (UU PDP Pasal 4): terenkripsi,
 * disembunyikan dari serialisasi, tidak masuk log audit, dan dihapus saat karyawan dinonaktifkan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdKaryawan
 * @property list<list<int>> $SidikWajah
 * @property list<string> $PathFoto
 * @property StatusWajahKaryawan $Status
 * @property Carbon $PersetujuanKaryawanPada
 * @property int|null $DitinjauOleh
 * @property Carbon|null $DitinjauPada
 * @property string|null $AlasanTolak
 * @property Carbon|null $DibuatPada
 */
final class WajahKaryawan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'WajahKaryawan';

    /** @var list<string> */
    protected $hidden = ['SidikWajah'];

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Menunggu', 'DitinjauOleh' => null, 'DitinjauPada' => null, 'AlasanTolak' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'SidikWajah' => 'encrypted:array',
            'PathFoto' => 'array',
            'Status' => StatusWajahKaryawan::class,
            'PersetujuanKaryawanPada' => 'datetime',
            'DitinjauPada' => 'datetime',
        ];
    }
}
