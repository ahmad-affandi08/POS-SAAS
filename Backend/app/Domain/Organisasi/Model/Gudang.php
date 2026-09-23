<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\JenisGudang;

/**
 * Lokasi stok (PRD §15.3). F-00 membuat satu gudang toko untuk "Outlet Utama".
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdOutlet
 * @property string $Kode
 * @property string $Nama
 * @property JenisGudang $Jenis
 */
final class Gudang extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Gudang';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => JenisGudang::class];
    }
}
