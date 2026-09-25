<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris faktur pembelian (F-04 fase 1), satu per baris GRN yang difakturkan (3-way matching). `HargaPenerimaan` &
 * `SubtotalPenerimaan` = harga GRN dan subtotal GRN untuk jumlah yang difakturkan; `Harga`/`Diskon`/`Subtotal` = angka
 * faktur pemasok; `NilaiPenerimaan` = hutang belum difakturkan (nilai GRN tersisa) yang ditutup baris ini;
 * `AlokasiOngkir` & `Pajak` = bagian ongkir & PPN faktur. Kolom `…Diretur` hanya diubah retur pembelian.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdFakturPembelian
 * @property int $Urutan
 * @property int $IdPenerimaanBarangDetail
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string $SimbolSatuan
 * @property string $Jumlah
 * @property string $JumlahDasar
 * @property string $HargaPenerimaan
 * @property string $SubtotalPenerimaan
 * @property string $Harga
 * @property string $Diskon
 * @property string $Subtotal
 * @property string $NilaiPenerimaan
 * @property string $AlokasiOngkir
 * @property string $Pajak
 * @property string $JumlahDiretur
 * @property string $NilaiDiretur
 * @property string $PajakDiretur
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class FakturPembelianDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'FakturPembelianDetail';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Diskon' => '0.00', 'AlokasiOngkir' => '0.00', 'Pajak' => '0.00', 'JumlahDiretur' => '0.0000', 'NilaiDiretur' => '0.00', 'PajakDiretur' => '0.00'];

    protected static function booted(): void
    {
        self::updating(function (self $baris): void {
            if (array_diff(array_keys($baris->getDirty()), ['JumlahDiretur', 'NilaiDiretur', 'PajakDiretur', 'DiubahPada']) !== []) {
                throw new LogicException('Baris faktur pembelian tidak bisa diubah.');
            }
        });
        self::deleting(function (): void {
            throw new LogicException('Baris faktur pembelian tidak pernah dihapus.');
        });
    }
}
