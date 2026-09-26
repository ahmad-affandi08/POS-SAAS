<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Saldo paket sesi pelanggan (F-16d bagian 2), satu per baris penjualan paket. `NilaiTersisa` = pendapatan diterima
 * dimuka yang belum diakui; sesi terakhir mengakui seluruh sisa nilai agar tidak ada selisih pembulatan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdPelanggan
 * @property int $IdPaketSesi
 * @property int $IdOutlet
 * @property int $IdPenjualan
 * @property int $IdPenjualanDetail
 * @property string $NomorPenjualan
 * @property string $NamaPaket
 * @property int $JumlahSesi
 * @property int $SisaSesi
 * @property string $NilaiAwal
 * @property string $NilaiTersisa
 * @property Carbon $TanggalBeli
 * @property Carbon|null $BerlakuSampai
 * @property StatusSaldoSesi $Status
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Pelanggan|null $Pelanggan
 */
final class SaldoSesi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'SaldoSesi';

    /**
     * @return BelongsTo<Pelanggan, $this>
     */
    public function Pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'IdPelanggan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusSaldoSesi::class,
            'JumlahSesi' => 'integer',
            'SisaSesi' => 'integer',
            'NilaiAwal' => 'decimal:2',
            'NilaiTersisa' => 'decimal:2',
            'TanggalBeli' => 'date',
            'BerlakuSampai' => 'date',
        ];
    }
}
