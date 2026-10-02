<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Model;

use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris perintah kerja (jasa atau sparepart). Nama produk, SKU, simbol satuan, dan harga di-**snapshot** dari price
 * engine server saat baris disimpan. `IdKaryawan` = mekanik baris jasa (komisi F-18 lewat staf baris penjualan saat
 * ditagih). `NomorSeri` = unit sparepart bernomor seri yang akan dipasang (boleh kosong, diisi kasir saat menagih). `Disetujui` = baris ini disetujui pelanggan (persetujuan boleh sebagian); hanya baris disetujui yang
 * diberikan ke kasir untuk ditagih. `Uuid` dipakai halaman persetujuan publik untuk memilih baris.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPerintahKerja
 * @property int $Urutan
 * @property JenisBarisPerintahKerja $Jenis
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string|null $Sku
 * @property int $IdProdukSatuan
 * @property string $SimbolSatuan
 * @property string $Jumlah
 * @property string $HargaSatuan
 * @property string $Diskon
 * @property string $Subtotal
 * @property bool|null $HargaTermasukPajak
 * @property int|null $IdKelompokPajak
 * @property int|null $IdKaryawan
 * @property string|null $Catatan
 * @property list<string>|null $NomorSeri
 * @property bool $Disetujui
 * @property-read PerintahKerja $PerintahKerja
 */
final class PerintahKerjaDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PerintahKerjaDetail';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Sku' => null,
        'Diskon' => '0.00',
        'HargaTermasukPajak' => null,
        'IdKelompokPajak' => null,
        'IdKaryawan' => null,
        'Catatan' => null,
        'NomorSeri' => null,
        'Disetujui' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisBarisPerintahKerja::class,
            'Jumlah' => 'decimal:4',
            'HargaSatuan' => 'decimal:2',
            'Diskon' => 'decimal:2',
            'Subtotal' => 'decimal:2',
            'HargaTermasukPajak' => 'boolean',
            'Disetujui' => 'boolean',
            'NomorSeri' => 'array',
        ];
    }

    /** @return BelongsTo<PerintahKerja, $this> */
    public function PerintahKerja(): BelongsTo
    {
        return $this->belongsTo(PerintahKerja::class, 'IdPerintahKerja', 'Id');
    }
}
