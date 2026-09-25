<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use LogicException;

/**
 * Refund retur penjualan (F-09 fase 1): tunai atau transfer manual. `JenisMetode`/`NamaMetode` = snapshot metode.
 * Append-only.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdReturPenjualan
 * @property int $Urutan
 * @property int $IdMetodePembayaran
 * @property JenisMetodePembayaran $JenisMetode
 * @property string $NamaMetode
 * @property string $Jumlah
 */
final class ReturPenjualanPembayaran extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ReturPenjualanPembayaran';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['JenisMetode' => JenisMetodePembayaran::class, 'Jumlah' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Refund retur penjualan tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Refund retur penjualan tidak boleh dihapus.');
        });
    }
}
