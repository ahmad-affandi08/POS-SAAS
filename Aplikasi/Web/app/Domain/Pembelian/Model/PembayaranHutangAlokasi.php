<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Alokasi satu pembayaran hutang ke satu faktur (F-04 fase 1). Append-only.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPembayaranHutang
 * @property int $IdFakturPembelian
 * @property string $Jumlah
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PembayaranHutangAlokasi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PembayaranHutangAlokasi';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Alokasi pembayaran hutang tidak bisa diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Alokasi pembayaran hutang tidak pernah dihapus.');
        });
    }
}
