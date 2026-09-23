<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Bahan satu versi resep (PRD §15.3, F-03). BR-03.4: tidak pernah diubah atau dihapus. `Jumlah` dalam satuan
 * `IdSatuan` seperti diisi; `JumlahDasar` = Jumlah × KonversiKeDasar bahan saat disimpan (HalfUp, 4 desimal).
 * `PersenSusut` = persen susut, 0 ≤ x < 100 (jumlah kotor = JumlahDasar ÷ (1 − PersenSusut/100)).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdResep
 * @property int $IdProdukBahan
 * @property string $Jumlah
 * @property int $IdSatuan
 * @property string $JumlahDasar
 * @property string $PersenSusut
 * @property int $Urutan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Produk $ProdukBahan
 * @property-read Satuan $Satuan
 */
final class ResepDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ResepDetail';

    /** @var array<string, mixed> */
    protected $attributes = ['PersenSusut' => '0'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('BR-03.4: bahan resep tidak boleh diubah. Simpan sebagai versi baru.'));
        self::deleting(fn (): never => throw new LogicException('BR-03.4: bahan resep tidak boleh dihapus.'));
    }

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function ProdukBahan(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProdukBahan', 'Id')->withTrashed();
    }

    /**
     * @return BelongsTo<Satuan, $this>
     */
    public function Satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class, 'IdSatuan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jumlah' => 'decimal:4', 'JumlahDasar' => 'decimal:4', 'PersenSusut' => 'decimal:6', 'Urutan' => 'integer'];
    }
}
