<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Status\StatusDataMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Versi harga paket (P-04, BR-P04.1, BR-P04.5). Harga terbit tidak diubah; satu-satunya pengecualian adalah
 * `BerlakuSampai` yang diisi sistem saat harga penggantinya terbit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdPaket
 * @property string $HargaBulanan
 * @property string $HargaTahunan
 * @property Carbon $BerlakuMulai
 * @property Carbon|null $BerlakuSampai
 * @property bool $TerapkanKePelangganLama
 * @property StatusDataMaster $Status
 * @property int|null $IdPenggunaPengelolaPengaju
 * @property Carbon|null $DiajukanPada
 * @property int $PutaranTinjauan
 * @property list<int>|null $DaftarIdPenyusun
 * @property-read Paket $Paket
 */
final class HargaPaket extends ModelDasar
{
    protected $table = 'HargaPaket';

    /** @var array<string, mixed> */
    protected $attributes = [
        'BerlakuSampai' => null,
        'TerapkanKePelangganLama' => false,
        'Status' => 'Draf',
        'IdPenggunaPengelolaPengaju' => null,
        'DiajukanPada' => null,
        'PutaranTinjauan' => 0,
        'DaftarIdPenyusun' => null,
    ];

    protected static function booted(): void
    {
        self::updating(static function (HargaPaket $harga): void {
            $kolomBerubah = array_diff(array_keys($harga->getDirty()), ['BerlakuSampai', self::UPDATED_AT]);

            if ($harga->getOriginal('Status') === StatusDataMaster::Terbit && $kolomBerubah !== []) {
                throw new LogicException('Harga paket terbit tidak boleh diubah (BR-P04.5).');
            }
        });

        self::deleting(static function (HargaPaket $harga): void {
            if ($harga->getOriginal('Status') === StatusDataMaster::Terbit) {
                throw new LogicException('Harga paket terbit tidak boleh dihapus (BR-P04.5).');
            }
        });
    }

    /**
     * @return BelongsTo<Paket, $this>
     */
    public function Paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class, 'IdPaket', 'Id');
    }

    public function AmbilHargaBulanan(): Uang
    {
        return Uang::Dari($this->HargaBulanan);
    }

    public function AmbilHargaTahunan(): Uang
    {
        return Uang::Dari($this->HargaTahunan);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'HargaBulanan' => 'decimal:2',
            'HargaTahunan' => 'decimal:2',
            'BerlakuMulai' => 'date',
            'BerlakuSampai' => 'date',
            'TerapkanKePelangganLama' => 'boolean',
            'Status' => StatusDataMaster::class,
            'DiajukanPada' => 'datetime',
            'PutaranTinjauan' => 'integer',
            'DaftarIdPenyusun' => 'array',
        ];
    }
}
