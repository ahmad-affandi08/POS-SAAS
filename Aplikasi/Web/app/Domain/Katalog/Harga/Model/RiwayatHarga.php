<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Riwayat perubahan harga (BR-03.3), **append-only**: satu baris per baris harga yang ditambah (`HargaLama` null),
 * diubah, atau dihapus (`HargaBaru` null), dengan pengubah (`DiubahOleh`) dan asal perubahan (`Sumber`). Ditulis
 * hanya oleh Aksi harga di `Katalog\Harga\Aksi`. `IdProdukSatuan` menjadi null bila satuan produk dihapus; `IdSatuan`
 * tetap menunjukkan satuannya.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int|null $IdProdukSatuan
 * @property int $IdSatuan
 * @property int|null $IdDaftarHarga
 * @property string $JumlahMinimum
 * @property string|null $HargaLama
 * @property string|null $HargaBaru
 * @property int|null $DiubahOleh
 * @property SumberPerubahanHarga $Sumber
 * @property Carbon|null $DibuatPada
 */
final class RiwayatHarga extends ModelDasar
{
    use MilikTenant;

    protected $table = 'RiwayatHarga';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('RiwayatHarga bersifat append-only dan tidak boleh diubah.');
        });

        self::deleting(static function (): never {
            throw new LogicException('RiwayatHarga bersifat append-only dan tidak boleh dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JumlahMinimum' => 'decimal:4',
            'HargaLama' => 'decimal:2',
            'HargaBaru' => 'decimal:2',
            'Sumber' => SumberPerubahanHarga::class,
        ];
    }
}
