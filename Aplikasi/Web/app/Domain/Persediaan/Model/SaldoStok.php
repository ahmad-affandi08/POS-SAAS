<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cache saldo stok per (produk, lokasi stok) = Σ `MutasiStok` (aturan #9, DesainF05a B.2). Hanya ditulis mesin buku
 * stok dan perintah bangun ulang, selalu di bawah kunci `FOR UPDATE` urut (IdProduk, IdGudang).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdGudang
 * @property string $JumlahTersedia
 * @property string $JumlahDipesan
 * @property string $NilaiPersediaan
 * @property string|null $HppRataRata
 * @property int|null $IdMutasiStokTerakhir
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read MutasiStok|null $MutasiStokTerakhir
 */
final class SaldoStok extends ModelDasar
{
    use MilikTenant;

    protected $table = 'SaldoStok';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'JumlahTersedia' => '0.0000',
        'JumlahDipesan' => '0.0000',
        'NilaiPersediaan' => '0.00',
        'HppRataRata' => null,
        'IdMutasiStokTerakhir' => null,
    ];

    /**
     * @return BelongsTo<MutasiStok, $this>
     */
    public function MutasiStokTerakhir(): BelongsTo
    {
        return $this->belongsTo(MutasiStok::class, 'IdMutasiStokTerakhir', 'Id');
    }

    /** Kunci peta `"{IdProduk}:{IdGudang}"` yang dipakai bersama mesin buku stok dan kueri saldo. */
    public static function BuatKunciPasangan(int $idProduk, int $idGudang): string
    {
        return $idProduk.':'.$idGudang;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JumlahTersedia' => 'string',
            'JumlahDipesan' => 'string',
            'NilaiPersediaan' => 'string',
            'HppRataRata' => 'string',
        ];
    }
}
