<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Baris dokumen stok awal (DesainF05a B.2). `NamaProduk`/`Sku` = snapshot saat disimpan. Jumlah dalam satuan dasar,
 * `HppSatuan` per satuan dasar, `Nilai` = Jumlah × HppSatuan dibulatkan 2 desimal. `KunciBatch` = kolom tersimpan.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdStokAwal
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string|null $Sku
 * @property string $Jumlah
 * @property string $HppSatuan
 * @property string $Nilai
 * @property string|null $NomorBatch
 * @property-read string $KunciBatch
 * @property Carbon|null $TanggalKedaluwarsa
 * @property list<string>|null $DaftarNomorSeri
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read StokAwal $StokAwal
 */
final class StokAwalDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'StokAwalDetail';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciBatch'];

    /** @var array<string, mixed> */
    protected $attributes = ['Sku' => null, 'NomorBatch' => null, 'TanggalKedaluwarsa' => null, 'DaftarNomorSeri' => null];

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
            'Urutan' => 'integer',
            'Jumlah' => 'string',
            'HppSatuan' => 'string',
            'Nilai' => 'string',
            'TanggalKedaluwarsa' => 'date',
            'DaftarNomorSeri' => 'array',
        ];
    }
}
