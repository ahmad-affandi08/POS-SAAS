<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Batch stok per (produk, lokasi stok, nomor batch) beserta kedaluwarsa (F-05g, DesainF05a B.2). `JumlahSisa` =
 * Σ `MutasiStok.Jumlah` baris batch ini; tidak pernah negatif. `HppSatuan` = HPP saat pertama diterima (informasi).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdGudang
 * @property string $NomorBatch
 * @property Carbon|null $TanggalKedaluwarsa
 * @property string $JumlahSisa
 * @property string|null $HppSatuan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class BatchStok extends ModelDasar
{
    use MilikTenant;

    protected $table = 'BatchStok';

    /** @var array<string, mixed> */
    protected $attributes = ['TanggalKedaluwarsa' => null, 'JumlahSisa' => '0.0000', 'HppSatuan' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['TanggalKedaluwarsa' => 'date', 'JumlahSisa' => 'string', 'HppSatuan' => 'string'];
    }
}
