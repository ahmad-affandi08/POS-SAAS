<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\JenisMutasiPoin;
use App\Domain\Pelanggan\Enum\SumberMutasiPoin;
use Illuminate\Support\Carbon;

/**
 * Buku poin pelanggan (F-16b, append-only kecuali `Sisa` baris positif yang dipakai FIFO). Saldo = Σ `Poin`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPelanggan
 * @property JenisMutasiPoin $Jenis
 * @property int $Poin
 * @property int|null $Sisa
 * @property SumberMutasiPoin $JenisSumber
 * @property int|null $IdSumber
 * @property int|null $IdSumberAsal
 * @property Carbon|null $KedaluwarsaPada
 * @property string|null $Keterangan
 * @property int|null $IdPengguna
 * @property Carbon|null $DibuatPada
 */
final class MutasiPoin extends ModelDasar
{
    use MilikTenant;

    protected $table = 'MutasiPoin';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisMutasiPoin::class,
            'JenisSumber' => SumberMutasiPoin::class,
            'Poin' => 'integer',
            'Sisa' => 'integer',
            'KedaluwarsaPada' => 'date',
        ];
    }
}
