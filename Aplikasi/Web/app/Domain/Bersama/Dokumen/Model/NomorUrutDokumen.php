<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Model;

use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Penghitung nomor dokumen per tenant, jenis, periode, dan (opsional) outlet/perangkat (DesainF05a B.2). Hanya
 * dinaikkan lewat `PenomorDokumen` di dalam transaksi dokumennya. `KunciOutlet`/`KunciPerangkat` = kolom tersimpan.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int|null $IdOutlet
 * @property int|null $IdPerangkat
 * @property JenisDokumenBernomor $JenisDokumen
 * @property string $Periode
 * @property int $NomorTerakhir
 * @property-read int $KunciOutlet
 * @property-read int $KunciPerangkat
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class NomorUrutDokumen extends ModelDasar
{
    use MilikTenant;

    protected $table = 'NomorUrutDokumen';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciOutlet', 'KunciPerangkat'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JenisDokumen' => JenisDokumenBernomor::class,
            'NomorTerakhir' => 'integer',
            'KunciOutlet' => 'integer',
            'KunciPerangkat' => 'integer',
        ];
    }
}
