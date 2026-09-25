<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Komisi satu karyawan atas satu baris penjualan (F-18, EMP-04). Bersih = `Jumlah` − `JumlahDibatalkan` (void/retur).
 * Hanya laporan; dijurnal saat rekap gaji (bagian 3).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdKaryawan
 * @property int $IdPenjualan
 * @property int $IdPenjualanDetail
 * @property int|null $IdAturanKomisi
 * @property int $IdOutlet
 * @property Carbon $TanggalBisnis
 * @property string $Dasar
 * @property string $Porsi
 * @property string $Jumlah
 * @property string $JumlahDibatalkan
 * @property string $DasarDibatalkan
 */
final class Komisi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Komisi';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['JumlahDibatalkan' => '0.00', 'DasarDibatalkan' => '0.00'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'Dasar' => 'decimal:2',
            'Porsi' => 'decimal:4',
            'Jumlah' => 'decimal:2',
            'JumlahDibatalkan' => 'decimal:2',
            'DasarDibatalkan' => 'decimal:2',
        ];
    }
}
