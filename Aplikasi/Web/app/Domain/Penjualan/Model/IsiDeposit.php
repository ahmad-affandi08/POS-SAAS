<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusIsiDeposit;
use Illuminate\Support\Carbon;

/**
 * Dokumen isi saldo deposit pelanggan dari POS (F-16d bagian 1, outbox `Deposit.Isi`, bisa offline). `Uuid` dibuat di
 * perangkat; nomor `DEP/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}`. Jurnal J-16.1 (`IdJurnal`): Dr akun metode,
 * Cr Saldo Deposit Pelanggan. Uang masuk kas/rekening shift (`IdShift`). Tidak diedit; pembatalan membalik jurnal
 * (`IdJurnalBatal`). `IdPelanggan` kosong = pelanggan belum dikenal server saat diterima (ditandai tinjauan).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int $IdShift
 * @property int|null $IdPelanggan
 * @property string $UuidPelanggan
 * @property int $IdPengguna
 * @property string $Nomor
 * @property Carbon $DibuatOfflinePada
 * @property Carbon $TanggalBisnis
 * @property int $IdMetodePembayaran
 * @property JenisMetodePembayaran $JenisMetode
 * @property string $NamaMetode
 * @property string $Jumlah
 * @property string|null $Referensi
 * @property StatusIsiDeposit $Status
 * @property int|null $IdJurnal
 * @property bool $PerluTinjauan
 * @property string|null $AlasanTinjauan
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property int|null $IdPembatal
 * @property int|null $IdJurnalBatal
 * @property Carbon $DiterimaPada
 * @property Carbon|null $DibuatPada
 */
final class IsiDeposit extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'IsiDeposit';

    protected $table = 'IsiDeposit';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JenisMetode' => JenisMetodePembayaran::class,
            'Status' => StatusIsiDeposit::class,
            'Jumlah' => 'decimal:2',
            'PerluTinjauan' => 'boolean',
            'DibuatOfflinePada' => 'datetime',
            'TanggalBisnis' => 'date',
            'DibatalkanPada' => 'datetime',
            'DiterimaPada' => 'datetime',
        ];
    }
}
