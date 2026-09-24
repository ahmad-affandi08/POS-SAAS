<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pembayaran penjualan (PRD §15.3, F-08). Tunai: `Jumlah` = uang diterima. `JenisMetode`/`NamaMetode` = snapshot
 * metode saat dibayar. Append-only (tidak diubah/dihapus).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPenjualan
 * @property int $Urutan
 * @property int $IdMetodePembayaran
 * @property JenisMetodePembayaran $JenisMetode
 * @property string $NamaMetode
 * @property string $Jumlah
 * @property string $Status
 * @property string|null $Referensi
 * @property string|null $RefEksternal
 * @property Carbon $DibayarPada
 */
final class PenjualanPembayaran extends ModelDasar
{
    use MilikTenant;

    /** Fase 1: pembayaran dikonfirmasi kasir di perangkat (tanpa gateway). */
    public const STATUS_DITERIMA = 'Diterima';

    protected $table = 'PenjualanPembayaran';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['JenisMetode' => JenisMetodePembayaran::class, 'Jumlah' => 'decimal:2', 'DibayarPada' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Pembayaran penjualan tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Pembayaran penjualan tidak boleh dihapus.');
        });
    }
}
