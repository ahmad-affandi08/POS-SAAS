<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusPemakaianSesi;
use Illuminate\Support\Carbon;

/**
 * Dokumen pemakaian sesi dari POS (outbox `Sesi.Pakai`, F-16d bagian 2). Tidak diedit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int|null $IdSaldoSesi
 * @property string $UuidSaldoSesi
 * @property int|null $IdPelanggan
 * @property int|null $IdProduk
 * @property string|null $NamaProduk
 * @property int $Jumlah
 * @property int $IdPengguna
 * @property Carbon $DibuatOfflinePada
 * @property Carbon $TanggalBisnis
 * @property string $NilaiDiakui
 * @property StatusPemakaianSesi $Status
 * @property int|null $IdJurnal
 * @property bool $PerluTinjauan
 * @property string|null $AlasanTinjauan
 * @property Carbon $DiterimaPada
 * @property Carbon|null $DibuatPada
 */
final class PemakaianSesi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PemakaianSesi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusPemakaianSesi::class,
            'Jumlah' => 'integer',
            'NilaiDiakui' => 'decimal:2',
            'PerluTinjauan' => 'boolean',
            'DibuatOfflinePada' => 'datetime',
            'TanggalBisnis' => 'date',
            'DiterimaPada' => 'datetime',
        ];
    }
}
