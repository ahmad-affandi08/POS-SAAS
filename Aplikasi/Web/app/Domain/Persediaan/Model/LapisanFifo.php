<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lapisan biaya FIFO (DesainF05a B.2, C.3). Satu lapisan per `MutasiStok` masuk (`IdMutasiSumber`); dikonsumsi
 * urut Id oleh mutasi keluar. `Habis` = `JumlahSisa` 0.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdGudang
 * @property int|null $IdBatchStok
 * @property int $IdMutasiSumber
 * @property Carbon $TanggalMasuk
 * @property string $JumlahAwal
 * @property string $JumlahSisa
 * @property string $HppSatuan
 * @property string $NilaiAwal
 * @property string $NilaiSisa
 * @property bool $Habis
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read MutasiStok $MutasiSumber
 * @property-read BatchStok|null $BatchStok
 */
final class LapisanFifo extends ModelDasar
{
    use MilikTenant;

    protected $table = 'LapisanFifo';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Habis' => false, 'IdBatchStok' => null];

    /**
     * @return BelongsTo<MutasiStok, $this>
     */
    public function MutasiSumber(): BelongsTo
    {
        return $this->belongsTo(MutasiStok::class, 'IdMutasiSumber', 'Id');
    }

    /**
     * @return BelongsTo<BatchStok, $this>
     */
    public function BatchStok(): BelongsTo
    {
        return $this->belongsTo(BatchStok::class, 'IdBatchStok', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalMasuk' => 'date',
            'JumlahAwal' => 'string',
            'JumlahSisa' => 'string',
            'HppSatuan' => 'string',
            'NilaiAwal' => 'string',
            'NilaiSisa' => 'string',
            'Habis' => 'boolean',
        ];
    }
}
