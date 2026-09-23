<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\Operasional\Enum\HasilBackup;
use App\Domain\Pengelola\Operasional\Enum\JenisCatatanBackup;
use App\Domain\Pengelola\Operasional\Enum\SumberCatatanBackup;
use Illuminate\Support\Carbon;

/**
 * Hasil backup atau uji restore (P-11, §14.5). Append-only: koreksi dengan catatan baru.
 *
 * @property int $Id
 * @property string $Uuid
 * @property JenisCatatanBackup $Jenis
 * @property HasilBackup $Hasil
 * @property Carbon $SelesaiPada
 * @property int|null $UkuranByte
 * @property string|null $Lokasi
 * @property string|null $Keterangan
 * @property SumberCatatanBackup $Sumber
 * @property int|null $IdPenggunaPengelola
 * @property Carbon $DibuatPada
 */
final class CatatanBackup extends ModelDasar
{
    protected $table = 'CatatanBackup';

    /** @var array<string, mixed> */
    protected $attributes = ['UkuranByte' => null, 'Lokasi' => null, 'Keterangan' => null, 'IdPenggunaPengelola' => null];

    protected static function booted(): void
    {
        self::updating(static fn (): bool => false);
        self::deleting(static fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisCatatanBackup::class,
            'Hasil' => HasilBackup::class,
            'SelesaiPada' => 'datetime',
            'UkuranByte' => 'integer',
            'Sumber' => SumberCatatanBackup::class,
        ];
    }
}
