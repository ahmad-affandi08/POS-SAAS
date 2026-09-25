<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\BentukMeja;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Meja di satu outlet (F-10a, PRD §9.1, §15 `Meja`). Nama unik per outlet ("7", "VIP 2"). Status pakai (kosong,
 * terisi, minta bill) diturunkan dari pesanan terbuka, bukan kolom. Diarsipkan, tidak dihapus.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int|null $IdAreaMeja
 * @property string $Nama
 * @property int $Kapasitas
 * @property BentukMeja $Bentuk
 * @property int|null $PosisiX
 * @property int|null $PosisiY
 * @property int $Urutan
 * @property StatusOrganisasi $Status
 * @property Carbon|null $DiarsipkanPada
 * @property-read AreaMeja|null $Area
 */
final class Meja extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Meja';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Aktif', 'DiarsipkanPada' => null, 'Bentuk' => 'Persegi', 'Kapasitas' => 4, 'Urutan' => 0];

    /**
     * @return BelongsTo<AreaMeja, $this>
     */
    public function Area(): BelongsTo
    {
        return $this->belongsTo(AreaMeja::class, 'IdAreaMeja', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Bentuk' => BentukMeja::class,
            'Status' => StatusOrganisasi::class,
            'DiarsipkanPada' => 'datetime',
            'Kapasitas' => 'integer',
            'PosisiX' => 'integer',
            'PosisiY' => 'integer',
            'Urutan' => 'integer',
        ];
    }
}
