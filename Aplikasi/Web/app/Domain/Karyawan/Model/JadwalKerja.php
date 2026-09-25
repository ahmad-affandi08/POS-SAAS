<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Jadwal kerja satu karyawan pada satu tanggal di satu outlet (F-18, EMP-02). `JamMulai`/`JamSelesai` `HH:mm` waktu
 * outlet; `JamSelesai` ≤ `JamMulai` = shift melewati tengah malam.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdKaryawan
 * @property int $IdOutlet
 * @property Carbon $Tanggal
 * @property string $JamMulai
 * @property string $JamSelesai
 */
final class JadwalKerja extends ModelDasar
{
    use MilikTenant;

    protected $table = 'JadwalKerja';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date'];
    }
}
