<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Pilihan menerima ringkasan pagi Kotak Tindakan lewat email (D-23 D bagian 4), satu per (tenant, pengguna).
 * `TerakhirDikirim` menjaga agar ringkasan terkirim paling banyak sekali per tanggal bisnis.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property bool $Aktif
 * @property Carbon|null $TerakhirDikirim
 */
final class LanggananRingkasanTindakan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'LanggananRingkasanTindakan';

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
