<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisKupon;
use Illuminate\Support\Carbon;

/**
 * Kupon langganan (P-04). Pemakaian (`KuponLanggananPemakaian`) dicatat oleh penagihan (P-08).
 * `Nilai` = persen (Jenis Persen) atau Rupiah (Jenis Nominal), string desimal. `DaftarKodePaket` null = semua paket.
 *
 * @property int $Id
 * @property string $Kode
 * @property JenisKupon $Jenis
 * @property string $Nilai
 * @property int $DurasiBulan
 * @property int|null $Kuota
 * @property list<string>|null $DaftarKodePaket
 * @property Carbon|null $BerlakuSampai
 * @property bool $Aktif
 */
final class KuponLangganan extends ModelDasar
{
    protected $table = 'KuponLangganan';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Kuota' => null, 'DaftarKodePaket' => null, 'BerlakuSampai' => null, 'Aktif' => true];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisKupon::class,
            'Nilai' => 'decimal:2',
            'DurasiBulan' => 'integer',
            'Kuota' => 'integer',
            'DaftarKodePaket' => 'array',
            'BerlakuSampai' => 'date',
            'Aktif' => 'boolean',
        ];
    }
}
