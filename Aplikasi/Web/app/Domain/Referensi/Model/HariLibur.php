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
 * Pembatalan hari libur terbit lewat pengajuan & tinjauan (BR-P02.6): hanya kolom pembatalan yang boleh berubah.
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
 * @property list<int>|null $DaftarIdPenyusun
 * @property Carbon|null $PembatalanDiajukanPada
 * @property int|null $IdPenggunaPengelolaPengajuBatal
 * @property string|null $AlasanPembatalan
 * @property Carbon|null $DibatalkanPada
 */
final class HariLibur extends ModelDasar
{
    /** Kolom yang boleh berubah pada hari libur terbit: hanya alur pembatalan (BR-P02.6). */
    private const KOLOM_PEMBATALAN = [
        'Status',
        'PutaranTinjauan',
        'PembatalanDiajukanPada',
        'IdPenggunaPengelolaPengajuBatal',
        'AlasanPembatalan',
        'DibatalkanPada',
        self::UPDATED_AT,
    ];

    protected $table = 'HariLibur';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'NomorDasarHukum' => null,
        'IdPenggunaPengelolaPengaju' => null,
        'DiajukanPada' => null,
        'PutaranTinjauan' => 0,
        'DaftarIdPenyusun' => null,
        'PembatalanDiajukanPada' => null,
        'IdPenggunaPengelolaPengajuBatal' => null,
        'AlasanPembatalan' => null,
        'DibatalkanPada' => null,
    ];

    protected static function booted(): void
    {
        self::updating(static function (HariLibur $hariLibur): void {
            $statusAsal = $hariLibur->getOriginal('Status');

            if ($statusAsal === StatusDataMaster::Dibatalkan) {
                throw new LogicException('Hari libur yang sudah dibatalkan tidak boleh diubah.');
            }

            if ($statusAsal !== StatusDataMaster::Terbit) {
                return;
            }

            $kolomBerubah = array_diff(array_keys($hariLibur->getDirty()), self::KOLOM_PEMBATALAN);
            $statusTidakSah = $hariLibur->isDirty('Status') && $hariLibur->Status !== StatusDataMaster::Dibatalkan;

            if ($kolomBerubah !== [] || $statusTidakSah) {
                throw new LogicException('Hari libur yang sudah terbit tidak boleh diubah, hanya dibatalkan (BR-P02.6).');
            }
        });

        self::deleting(static function (HariLibur $hariLibur): void {
            if ($hariLibur->getOriginal('Status') !== StatusDataMaster::Draf) {
                throw new LogicException('Hanya hari libur berstatus draf yang boleh dihapus.');
            }
        });
    }

    public function CekPembatalanMenunggu(): bool
    {
        return $this->Status === StatusDataMaster::Terbit && $this->PembatalanDiajukanPada !== null;
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
            'DaftarIdPenyusun' => 'array',
            'PembatalanDiajukanPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
