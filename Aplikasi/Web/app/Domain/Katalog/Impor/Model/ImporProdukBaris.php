<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Impor\Enum\AksiBarisImpor;
use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris berkas impor produk (F-03 BR-03.6). `Data` = nilai ternormalisasi per `BidangImpor`, `DataAsli` =
 * isi sel asli per judul kolom (untuk laporan galat), `Galat` = `[{Bidang, Pesan}]`. `NomorBaris` = nomor baris
 * spreadsheet (mulai 1). Status `Diterapkan` ditulis di transaksi yang sama dengan produknya.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdImporProduk
 * @property int $NomorBaris
 * @property StatusBarisImpor $Status
 * @property AksiBarisImpor|null $Aksi
 * @property string|null $KunciProduk
 * @property array<string, mixed> $Data
 * @property array<string, string> $DataAsli
 * @property list<array{Bidang: string, Pesan: string}>|null $Galat
 * @property int|null $IdProduk
 * @property Carbon|null $DiterapkanPada
 */
final class ImporProdukBaris extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ImporProdukBaris';

    protected bool $pakaiUuid = false;

    /**
     * @return BelongsTo<ImporProduk, $this>
     */
    public function ImporProduk(): BelongsTo
    {
        return $this->belongsTo(ImporProduk::class, 'IdImporProduk', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusBarisImpor::class,
            'Aksi' => AksiBarisImpor::class,
            'Data' => 'array',
            'DataAsli' => 'array',
            'Galat' => 'array',
            'DiterapkanPada' => 'datetime',
        ];
    }
}
