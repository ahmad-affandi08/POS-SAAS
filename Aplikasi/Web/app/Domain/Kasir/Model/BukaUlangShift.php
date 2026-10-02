<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * K-18: log buka ulang shift oleh supervisor (append-only). `SnapshotTutup` = data tutup shift sebelum dibuka ulang.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdShift
 * @property int $IdPerangkat
 * @property int $Urutan
 * @property string $Alasan
 * @property int $DimintaOleh
 * @property int $DisetujuiOleh
 * @property Carbon $DibukaUlangPada
 * @property array{DitutupOleh: int|null, DitutupPada: string|null, KasSeharusnya: string|null, KasAktual: string|null, Selisih: string|null, AlasanSelisih: string|null} $SnapshotTutup
 * @property int|null $IdJurnalPembalik
 */
final class BukaUlangShift extends ModelDasar
{
    use MilikTenant;

    protected $table = 'BukaUlangShift';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'DibukaUlangPada' => 'datetime',
            'SnapshotTutup' => 'array',
            'Urutan' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Log buka ulang shift append-only: tidak boleh diubah.');
        });

        self::deleting(function (): void {
            throw new LogicException('Log buka ulang shift tidak boleh dihapus.');
        });
    }
}
