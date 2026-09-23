<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Referensi\Enum\JenisHariLibur;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Hari libur nasional & cuti bersama (P-02, PRD §15.3). Diajukan & ditinjau per tahun (1 penyetuju, BR-P02.2).
 * Baris yang sudah terbit tidak diubah atau dihapus; tambahan cuti bersama = baris draf baru.
 *
 * @property int $Id
 * @property string $Uuid
 * @property Carbon $Tanggal
 * @property string $Nama
 * @property JenisHariLibur $Jenis
 * @property StatusDataMaster $Status
 * @property string|null $NomorDasarHukum
 * @property int|null $IdPenggunaPengelolaPengaju
 * @property Carbon|null $DiajukanPada
 * @property int $PutaranTinjauan
 */
final class HariLibur extends ModelDasar
{
    protected $table = 'HariLibur';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'NomorDasarHukum' => null,
        'IdPenggunaPengelolaPengaju' => null,
        'DiajukanPada' => null,
        'PutaranTinjauan' => 0,
    ];

    protected static function booted(): void
    {
        self::updating(static function (HariLibur $hariLibur): void {
            if ($hariLibur->getOriginal('Status') === StatusDataMaster::Terbit) {
                throw new LogicException('Hari libur yang sudah terbit tidak boleh diubah.');
            }
        });

        self::deleting(static function (HariLibur $hariLibur): void {
            if ($hariLibur->getOriginal('Status') !== StatusDataMaster::Draf) {
                throw new LogicException('Hanya hari libur berstatus draf yang boleh dihapus.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'Jenis' => JenisHariLibur::class,
            'Status' => StatusDataMaster::class,
            'DiajukanPada' => 'datetime',
            'PutaranTinjauan' => 'integer',
        ];
    }
}
