<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\AlasanPemulihanSinkron;
use Illuminate\Support\Carbon;

/**
 * Penanda tinjauan item outbox yang diterima lewat jalur pemulihan (audit P0 F-01, BR-02.3). Hanya ditambah, tidak
 * diubah; "sudah dicek" lewat `TinjauanDokumen` (jenis `ItemSinkronPemulihan`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPerangkat
 * @property int $IdPerangkatPengirim
 * @property int $IdOutlet
 * @property string $Jenis
 * @property AlasanPemulihanSinkron $Alasan
 * @property Carbon $DibuatPadaKlien
 * @property Carbon|null $DibuatPada
 */
final class ItemSinkronPemulihan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ItemSinkronPemulihan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Alasan' => AlasanPemulihanSinkron::class,
            'DibuatPadaKlien' => 'datetime',
        ];
    }
}
