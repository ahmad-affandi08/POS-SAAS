<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use LogicException;

/**
 * Rincian pajak per dokumen per jenis pajak (PRD §15.3 v1.43, F-07b): snapshot tarif & pengali DPP, dasar pengenaan,
 * DPP, dan jumlah (dibulatkan per dokumen, F-07a). Append-only.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPenjualan
 * @property string $KodeJenisPajak
 * @property string $Tarif
 * @property int $PengaliDppPembilang
 * @property int $PengaliDppPenyebut
 * @property DasarPengenaanPajak $DasarPengenaan
 * @property string $Dpp
 * @property string $Jumlah
 */
final class PenjualanPajak extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenjualanPajak';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tarif' => 'decimal:6',
            'PengaliDppPembilang' => 'integer',
            'PengaliDppPenyebut' => 'integer',
            'DasarPengenaan' => DasarPengenaanPajak::class,
            'Dpp' => 'decimal:2',
            'Jumlah' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Rincian pajak penjualan tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Rincian pajak penjualan tidak boleh dihapus.');
        });
    }
}
