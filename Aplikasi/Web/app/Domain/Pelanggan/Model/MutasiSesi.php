<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use Illuminate\Support\Carbon;

/**
 * Buku sesi (F-16d bagian 2), append-only: sisa = Σ `JumlahSesi`, nilai diterima dimuka = Σ `Nilai` per saldo sesi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdSaldoSesi
 * @property int|null $IdPelanggan
 * @property JenisMutasiSesi $Jenis
 * @property int $JumlahSesi
 * @property string $Nilai
 * @property int $SisaSetelah
 * @property SumberMutasiSesi $JenisSumber
 * @property int|null $IdSumber
 * @property string|null $NomorSumber
 * @property Carbon $Tanggal
 * @property int|null $IdAkunKasBank
 * @property int|null $IdJurnal
 * @property string|null $Keterangan
 * @property int|null $IdPengguna
 * @property Carbon|null $DibuatPada
 */
final class MutasiSesi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'MutasiSesi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisMutasiSesi::class,
            'JenisSumber' => SumberMutasiSesi::class,
            'JumlahSesi' => 'integer',
            'Nilai' => 'decimal:2',
            'SisaSetelah' => 'integer',
            'Tanggal' => 'date',
        ];
    }
}
