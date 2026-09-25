<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris GRN (F-04 fase 1). `Jumlah` dalam satuan pembelian, `JumlahDasar` = Jumlah × Konversi; `Subtotal` = Jumlah ×
 * Harga − Diskon; `AlokasiBiaya` = bagian ongkir (dan PPN yang tidak dapat dikreditkan) sebanding Subtotal; `Nilai` =
 * Subtotal + AlokasiBiaya = nilai mutasi `PenerimaanPembelian` (harga landed, BR-04.2); `HppSatuan` = Nilai ÷
 * JumlahDasar. `JumlahDiretur`/`NilaiDiretur` (satuan dasar) hanya diubah retur pembelian & pembatalannya.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPenerimaanBarang
 * @property int $Urutan
 * @property int|null $IdPesananPembelianDetail
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string|null $Sku
 * @property int|null $IdProdukSatuan
 * @property string $SimbolSatuan
 * @property string $Konversi
 * @property string $Jumlah
 * @property string $JumlahDasar
 * @property string $Harga
 * @property string $Diskon
 * @property string $Subtotal
 * @property string $AlokasiBiaya
 * @property string $Nilai
 * @property string $HppSatuan
 * @property string|null $NomorBatch
 * @property Carbon|null $TanggalKedaluwarsa
 * @property list<string>|null $DaftarNomorSeri
 * @property int|null $IdBatchStok
 * @property string $JumlahDiretur
 * @property string $NilaiDiretur
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read PenerimaanBarang $PenerimaanBarang
 */
final class PenerimaanBarangDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenerimaanBarangDetail';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdPesananPembelianDetail' => null,
        'Sku' => null,
        'IdProdukSatuan' => null,
        'Diskon' => '0.00',
        'AlokasiBiaya' => '0.00',
        'NomorBatch' => null,
        'TanggalKedaluwarsa' => null,
        'DaftarNomorSeri' => null,
        'IdBatchStok' => null,
        'JumlahDiretur' => '0.0000',
        'NilaiDiretur' => '0.00',
    ];

    /** Aturan #8: baris GRN tidak pernah dihapus; setelah dibuat hanya kolom retur yang berubah. */
    protected static function booted(): void
    {
        self::updating(function (self $baris): void {
            if (array_diff(array_keys($baris->getDirty()), ['JumlahDiretur', 'NilaiDiretur', 'IdBatchStok', 'DiubahPada']) !== []) {
                throw new LogicException('Baris penerimaan barang yang sudah diposting tidak bisa diubah.');
            }
        });
        self::deleting(function (): void {
            throw new LogicException('Baris penerimaan barang tidak pernah dihapus.');
        });
    }

    /**
     * @return BelongsTo<PenerimaanBarang, $this>
     */
    public function PenerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'IdPenerimaanBarang', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['TanggalKedaluwarsa' => 'date', 'DaftarNomorSeri' => 'array'];
    }
}
