<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use Illuminate\Support\Carbon;

/**
 * Area meja di satu outlet, misal "Indoor", "Teras", "VIP" (F-10a, PRD §9.1). Diarsipkan, tidak dihapus.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property string $Nama
 * @property int $Urutan
 * @property StatusOrganisasi $Status
 * @property Carbon|null $DiarsipkanPada
 */
final class AreaMeja extends ModelDasar
{
    use MilikTenant;

    protected $table = 'AreaMeja';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Aktif', 'DiarsipkanPada' => null, 'Urutan' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Status' => StatusOrganisasi::class, 'DiarsipkanPada' => 'datetime', 'Urutan' => 'integer'];
    }
}
