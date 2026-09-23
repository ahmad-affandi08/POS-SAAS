<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use Illuminate\Support\Carbon;

/**
 * Jejak penghapusan baris katalog (F-03, append-only) untuk bagian `Terhapus` katalog POS. Ditulis hanya lewat
 * `PencatatPenghapusanKatalog`; tidak pernah diubah.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property EntitasKatalog $Entitas
 * @property string $UuidEntitas
 * @property Carbon $DihapusPada
 */
final class PenghapusanKatalog extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenghapusanKatalog';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Entitas' => EntitasKatalog::class, 'DihapusPada' => 'datetime'];
    }
}
