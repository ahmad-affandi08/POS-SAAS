<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pembayaran hutang `BH/{YYMM}/{SEQ4}` (F-04 fase 1) dari akun kas/bank; alokasi ke faktur satu pemasok; jurnal J-04.4
 * saat simpan. Tidak pernah diubah; pembatalan = jurnal pembalik & alokasi dikembalikan ke sisa faktur.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property int|null $IdPemasok
 * @property int $IdAkun
 * @property int|null $IdOutlet
 * @property Carbon $Tanggal
 * @property string $Jumlah
 * @property StatusDokumenPembelian $Status
 * @property bool $BelanjaStok
 * @property string|null $Catatan
 * @property string|null $PathLampiran
 * @property string|null $NamaLampiran
 * @property string|null $MimeLampiran
 * @property int|null $UkuranLampiran
 * @property int|null $IdJurnal
 * @property int|null $IdJurnalPembatalan
 * @property int|null $DibuatOleh
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, PembayaranHutangAlokasi> $Alokasi
 */
final class PembayaranHutang extends ModelDasar
{
    use JagaDokumenPembelian;
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PembayaranHutang';

    protected $table = 'PembayaranHutang';

    /** @var list<string> */
    protected $hidden = ['PathLampiran'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Diposting',
        'IdOutlet' => null,
        'BelanjaStok' => false,
        'Catatan' => null,
        'PathLampiran' => null,
        'NamaLampiran' => null,
        'MimeLampiran' => null,
        'UkuranLampiran' => null,
        'IdJurnal' => null,
        'IdJurnalPembatalan' => null,
    ];

    /**
     * @return list<string>
     */
    public function AmbilKolomBolehBerubah(): array
    {
        return ['Status', 'IdJurnal', 'IdJurnalPembatalan', 'DibatalkanOleh', 'DibatalkanPada', 'AlasanBatal'];
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusDokumenPembelian $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pembayaran hutang {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return HasMany<PembayaranHutangAlokasi, $this>
     */
    public function Alokasi(): HasMany
    {
        return $this->hasMany(PembayaranHutangAlokasi::class, 'IdPembayaranHutang', 'Id')->orderBy('Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'Status' => StatusDokumenPembelian::class,
            'BelanjaStok' => 'boolean',
            'UkuranLampiran' => 'integer',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
