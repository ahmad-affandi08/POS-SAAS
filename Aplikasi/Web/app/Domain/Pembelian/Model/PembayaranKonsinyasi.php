<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Dokumen\Model\JagaDokumenTerposting;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Setoran hasil penjualan titipan ke penitip `BK/{YYMM}/{SEQ4}` (F-05i): Dr Hutang Konsinyasi / Cr kas-bank, paling
 * banyak sisa hutang penitip itu. Tidak pernah diubah; pembatalan = jurnal pembalik.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property int $IdPemasok
 * @property Carbon $Tanggal
 * @property string $Jumlah
 * @property int $IdAkunKasBank
 * @property StatusDokumenTerposting $Status
 * @property int|null $IdJurnal
 * @property int|null $IdJurnalPembatalan
 * @property string|null $Catatan
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PembayaranKonsinyasi extends ModelDasar
{
    use JagaDokumenTerposting;
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PembayaranKonsinyasi';

    protected $table = 'PembayaranKonsinyasi';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Diposting', 'Catatan' => null, 'IdJurnal' => null, 'IdJurnalPembatalan' => null];

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
    public function UbahStatus(StatusDokumenTerposting $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status setoran konsinyasi {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date', 'Status' => StatusDokumenTerposting::class, 'DibatalkanPada' => 'datetime'];
    }
}
