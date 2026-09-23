<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use Illuminate\Support\Carbon;

/**
 * Lokasi stok (PRD §15.3). F-00 membuat satu gudang toko untuk "Outlet Utama"; F-02 menambah lokasi lain per
 * outlet (Dapur, Bar, Gudang Belakang, ...). Tidak pernah dihapus, hanya diarsipkan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdOutlet
 * @property string $Kode
 * @property string $Nama
 * @property JenisGudang $Jenis
 * @property StatusOrganisasi $Status
 * @property Carbon|null $DiarsipkanPada
 */
final class Gudang extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Gudang';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Aktif', 'DiarsipkanPada' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => JenisGudang::class, 'Status' => StatusOrganisasi::class, 'DiarsipkanPada' => 'datetime'];
    }
}
