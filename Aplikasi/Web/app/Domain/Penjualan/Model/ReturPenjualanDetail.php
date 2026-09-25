<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Baris retur penjualan (PRD §15 `ReturPenjualanDetail`, F-09 fase 1) dengan snapshot nilai proporsional dari baris
 * penjualan asal. Append-only.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdReturPenjualan
 * @property int $IdPenjualanDetail
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string $Jumlah
 * @property string $JumlahDasar
 * @property string $NilaiBaris
 * @property string $Pajak
 * @property string $BiayaLayanan
 * @property string $HppSatuan
 * @property string $TotalHpp
 * @property KondisiBarangRetur $Kondisi
 * @property int|null $IdGudang
 */
final class ReturPenjualanDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ReturPenjualanDetail';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jumlah' => 'decimal:4',
            'JumlahDasar' => 'decimal:4',
            'NilaiBaris' => 'decimal:2',
            'Pajak' => 'decimal:2',
            'BiayaLayanan' => 'decimal:2',
            'HppSatuan' => 'decimal:6',
            'TotalHpp' => 'decimal:2',
            'Kondisi' => KondisiBarangRetur::class,
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Baris retur penjualan tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Baris retur penjualan tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<ReturPenjualan, $this>
     */
    public function Retur(): BelongsTo
    {
        return $this->belongsTo(ReturPenjualan::class, 'IdReturPenjualan', 'Id');
    }
}
