<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu versi resep/BOM produk (PRD §15.3, F-03). BR-03.4: versi tidak pernah diubah atau dihapus; perubahan resep
 * selalu membuat versi baru (`Versi` = terbesar + 1), sehingga snapshot di baris penjualan (F-07) tetap bisa dirujuk.
 * `JumlahHasil` (yield) dalam satuan dasar produk.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $Versi
 * @property string $JumlahHasil
 * @property string|null $Catatan
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, ResepDetail> $Detail
 * @property-read Produk $Produk
 */
final class Resep extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Resep';

    /** @var array<string, mixed> */
    protected $attributes = ['Catatan' => null, 'DibuatOleh' => null];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('BR-03.4: versi resep tidak boleh diubah. Simpan sebagai versi baru.'));
        self::deleting(fn (): never => throw new LogicException('BR-03.4: versi resep tidak boleh dihapus.'));
    }

    /**
     * @return HasMany<ResepDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(ResepDetail::class, 'IdResep', 'Id')->orderBy('Urutan');
    }

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function Produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'IdProduk', 'Id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Versi' => 'integer', 'JumlahHasil' => 'decimal:4'];
    }
}
