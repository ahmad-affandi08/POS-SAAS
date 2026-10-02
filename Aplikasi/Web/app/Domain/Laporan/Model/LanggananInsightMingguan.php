<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Pilihan menerima insight mingguan X6 lewat email (v3.79), satu per (tenant, pengguna). `TerakhirDikirim` = Senin
 * minggu laporan terakhir yang terkirim, supaya paling banyak sekali per minggu.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property bool $Aktif
 * @property Carbon|null $TerakhirDikirim
 */
final class LanggananInsightMingguan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'LanggananInsightMingguan';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => true, 'TerakhirDikirim' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'TerakhirDikirim' => 'date'];
    }
}
