<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Pengaturan pengingat piutang otomatis tenant (D-23 D bagian 4b). Tanpa baris = mati.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $Aktif
 * @property int $HariSebelum 0–14; 0 = hanya pada hari jatuh tempo
 * @property bool $IngatkanSaatLewat
 */
final class PengaturanPengingatPiutang extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanPengingatPiutang';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => false, 'HariSebelum' => 3, 'IngatkanSaatLewat' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'HariSebelum' => 'integer', 'IngatkanSaatLewat' => 'boolean'];
    }
}
