<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusPelanggan;

/**
 * Tier pelanggan (F-16b, CRM-02). `Kode` dirujuk `DaftarHarga.TierPelanggan`; `MinimalBelanja` = ambang belanja N bulan
 * untuk evaluasi otomatis; `PengaliPoin` mengalikan perolehan poin.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Kode
 * @property string $Nama
 * @property string $MinimalBelanja
 * @property string $PengaliPoin
 * @property int $Urutan
 * @property StatusPelanggan $Status
 */
final class TierPelanggan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TierPelanggan';

    /** @var array<string, mixed> */
    protected $attributes = ['MinimalBelanja' => '0.00', 'PengaliPoin' => '1.00', 'Urutan' => 0, 'Status' => 'Aktif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Urutan' => 'integer', 'Status' => StatusPelanggan::class];
    }
}
