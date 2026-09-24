<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga jual produk per satuan (PRD §15.3). `IdDaftarHarga` null = harga dasar; selain itu harga di daftar harga
 * (F-03). `JumlahMinimum` = harga berlaku mulai jumlah itu (1 = dasar, lebih besar = bertingkat). `Harga` = string
 * desimal 2 angka (tidak pernah float). `KunciDaftarHarga` = kolom turunan DB `IFNULL(IdDaftarHarga, 0)` untuk indeks
 * unik; jangan diisi. Ditulis hanya lewat Aksi `Katalog\Harga\Aksi` (mencatat `RiwayatHarga`, BR-03.3).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdProdukSatuan
 * @property int|null $IdDaftarHarga
 * @property int $KunciDaftarHarga
 * @property string $JumlahMinimum
 * @property string $Harga
 * @property-read DaftarHarga|null $DaftarHarga
 */
final class ProdukHarga extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukHarga';

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciDaftarHarga'];

    /** @var array<string, mixed> */
    protected $attributes = ['IdDaftarHarga' => null, 'JumlahMinimum' => '1'];

    /**
     * @return BelongsTo<DaftarHarga, $this>
     */
    public function DaftarHarga(): BelongsTo
    {
        return $this->belongsTo(DaftarHarga::class, 'IdDaftarHarga', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['JumlahMinimum' => 'decimal:4', 'Harga' => 'decimal:2'];
    }
}
