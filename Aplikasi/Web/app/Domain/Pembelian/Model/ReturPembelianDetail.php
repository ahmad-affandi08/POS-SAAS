<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris retur pembelian (F-04 fase 1) dalam satuan dasar. `Nilai` = HPP penerimaan untuk jumlah ini (nilai mutasi
 * yang diminta); `NilaiHutang` = pengurang hutang barang (harga faktur bila sudah difakturkan, selain itu = Nilai);
 * `Pajak` = PPN faktur yang ikut dikurangi. Append-only.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdReturPembelian
 * @property int $Urutan
 * @property int $IdPenerimaanBarangDetail
 * @property int|null $IdFakturPembelianDetail
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string $SimbolSatuan
 * @property string $JumlahDasar
 * @property string $Nilai
 * @property string $NilaiHutang
 * @property string $Pajak
 * @property int|null $IdBatchStok
 * @property list<string>|null $DaftarNomorSeri
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ReturPembelianDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ReturPembelianDetail';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['IdFakturPembelianDetail' => null, 'Pajak' => '0.00', 'IdBatchStok' => null, 'DaftarNomorSeri' => null];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Baris retur pembelian tidak bisa diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Baris retur pembelian tidak pernah dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DaftarNomorSeri' => 'array'];
    }
}
