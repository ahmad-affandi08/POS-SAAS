<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris PO (F-04 fase 1): `Jumlah` & `JumlahDiterima` dalam satuan pembelian (`SimbolSatuan`, `Konversi` ke satuan
 * dasar di-snapshot); `Harga` per satuan pembelian; `Subtotal` = Jumlah × Harga − Diskon. Setelah PO keluar dari
 * Draf hanya `JumlahDiterima` yang berubah (oleh penerimaan barang dan pembatalannya).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPesananPembelian
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string|null $Sku
 * @property int|null $IdProdukSatuan
 * @property string $SimbolSatuan
 * @property string $Konversi
 * @property string $Jumlah
 * @property string $Harga
 * @property string $Diskon
 * @property string $Subtotal
 * @property string $JumlahDiterima
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read PesananPembelian $PesananPembelian
 */
final class PesananPembelianDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananPembelianDetail';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Sku' => null, 'IdProdukSatuan' => null, 'Diskon' => '0.00', 'JumlahDiterima' => '0.0000'];

    /**
     * Penjaga aturan #8: baris hanya boleh dihapus atau diubah bebas selama PO masih Draf; setelah itu hanya
     * `JumlahDiterima`.
     */
    protected static function booted(): void
    {
        $cekDraf = fn (self $baris): bool => PesananPembelian::query()->whereKey($baris->IdPesananPembelian)->value('Status') === StatusPesananPembelian::Draf;

        self::updating(function (self $baris) use ($cekDraf): void {
            if (! $cekDraf($baris) && array_diff(array_keys($baris->getDirty()), ['JumlahDiterima', 'DiubahPada']) !== []) {
                throw new LogicException('Baris pesanan pembelian di luar Draf hanya boleh berubah jumlah diterimanya.');
            }
        });
        self::deleting(function (self $baris) use ($cekDraf): void {
            if (! $cekDraf($baris)) {
                throw new LogicException('Baris pesanan pembelian hanya bisa dihapus selama PO masih Draf.');
            }
        });
    }

    /**
     * @return BelongsTo<PesananPembelian, $this>
     */
    public function PesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'IdPesananPembelian', 'Id');
    }
}
