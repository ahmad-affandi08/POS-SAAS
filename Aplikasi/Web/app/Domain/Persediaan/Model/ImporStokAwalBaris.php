<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\StatusBarisImporStokAwal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris berkas impor stok awal hasil validasi (DesainF05a B.2): data ternormalisasi, data asli, galat, dan
 * dokumen Draf tujuannya (`IdStokAwal`, diisi di transaksi yang sama dengan pembuatan draf agar bisa dilanjutkan).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdImporStokAwal
 * @property int $NomorBaris
 * @property StatusBarisImporStokAwal $Status
 * @property array<string, mixed>|null $Data
 * @property array<string, mixed> $DataAsli
 * @property list<array{Bidang: string, Pesan: string}>|null $Galat
 * @property int|null $IdStokAwal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read ImporStokAwal $ImporStokAwal
 * @property-read StokAwal|null $StokAwal
 */
final class ImporStokAwalBaris extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ImporStokAwalBaris';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Data' => null, 'Galat' => null, 'IdStokAwal' => null];

    /**
     * @return BelongsTo<ImporStokAwal, $this>
     */
    public function ImporStokAwal(): BelongsTo
    {
        return $this->belongsTo(ImporStokAwal::class, 'IdImporStokAwal', 'Id');
    }

    /**
     * @return BelongsTo<StokAwal, $this>
     */
    public function StokAwal(): BelongsTo
    {
        return $this->belongsTo(StokAwal::class, 'IdStokAwal', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'NomorBaris' => 'integer',
            'Status' => StatusBarisImporStokAwal::class,
            'Data' => 'array',
            'DataAsli' => 'array',
            'Galat' => 'array',
        ];
    }
}
