<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Status\StatusDataMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Tarif pajak master platform, bertanggal berlaku (P-02, PRD §12.2, §15.3).
 *
 * - `Tarif` dalam persen (string desimal 6 angka, misal "12.000000"). Tidak pernah float.
 * - `PengaliDpp` = PengaliDppPembilang / PengaliDppPenyebut (pecahan eksak, misal 11/12).
 * - `KodeWilayah` null berarti tarif nasional.
 * - BR-P02.1: tarif Terbit tidak pernah diubah; satu-satunya pengecualian adalah `BerlakuSampai` yang diisi sistem
 *   sekali saat tarif penggantinya terbit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdJenisPajak
 * @property string $Tarif
 * @property int $PengaliDppPembilang
 * @property int $PengaliDppPenyebut
 * @property string|null $KodeWilayah
 * @property bool $BiayaLayananMasukDpp
 * @property Carbon $BerlakuMulai
 * @property Carbon|null $BerlakuSampai
 * @property StatusDataMaster $Status
 * @property string|null $NomorDasarHukum
 * @property string|null $TautanDasarHukum
 * @property int|null $IdPenggunaPengelolaPengaju
 * @property Carbon|null $DiajukanPada
 * @property int $PutaranTinjauan
 * @property-read JenisPajak $JenisPajak
 */
final class TarifPajak extends ModelDasar
{
    protected $table = 'TarifPajak';

    /** @var array<string, mixed> */
    protected $attributes = [
        'PengaliDppPembilang' => 1,
        'PengaliDppPenyebut' => 1,
        'KodeWilayah' => null,
        'BiayaLayananMasukDpp' => false,
        'BerlakuSampai' => null,
        'Status' => 'Draf',
        'NomorDasarHukum' => null,
        'TautanDasarHukum' => null,
        'IdPenggunaPengelolaPengaju' => null,
        'DiajukanPada' => null,
        'PutaranTinjauan' => 0,
    ];

    protected static function booted(): void
    {
        // BR-P02.1 ditegakkan juga di lapisan model, tidak hanya di Aksi.
        self::updating(static function (TarifPajak $tarif): void {
            $kolomBerubah = array_diff(array_keys($tarif->getDirty()), ['BerlakuSampai', self::UPDATED_AT]);

            if ($tarif->getOriginal('Status') === StatusDataMaster::Terbit && $kolomBerubah !== []) {
                throw new LogicException('Tarif pajak terbit tidak boleh diubah (BR-P02.1).');
            }
        });

        self::deleting(static function (TarifPajak $tarif): void {
            if ($tarif->getOriginal('Status') === StatusDataMaster::Terbit) {
                throw new LogicException('Tarif pajak terbit tidak boleh dihapus (BR-P02.1).');
            }
        });
    }

    /**
     * @return BelongsTo<JenisPajak, $this>
     */
    public function JenisPajak(): BelongsTo
    {
        return $this->belongsTo(JenisPajak::class, 'IdJenisPajak', 'Id');
    }

    public function CekNasional(): bool
    {
        return $this->KodeWilayah === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tarif' => 'decimal:6',
            'PengaliDppPembilang' => 'integer',
            'PengaliDppPenyebut' => 'integer',
            'BiayaLayananMasukDpp' => 'boolean',
            'BerlakuMulai' => 'date',
            'BerlakuSampai' => 'date',
            'Status' => StatusDataMaster::class,
            'DiajukanPada' => 'datetime',
            'PutaranTinjauan' => 'integer',
        ];
    }
}
