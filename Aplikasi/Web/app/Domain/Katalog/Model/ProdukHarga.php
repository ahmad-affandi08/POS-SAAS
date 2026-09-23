<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Harga jual produk per satuan (PRD §15.3). `IdDaftarHarga` null = harga umum; F-03 menambahkan daftar harga &
 * harga bertingkat (`JumlahMinimum`). `Harga` = string desimal 2 angka (tidak pernah float).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdProdukSatuan
 * @property int|null $IdDaftarHarga
 * @property string $JumlahMinimum
 * @property string $Harga
 */
final class ProdukHarga extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ProdukHarga';

    /** @var array<string, mixed> */
    protected $attributes = ['IdDaftarHarga' => null, 'JumlahMinimum' => '1'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['JumlahMinimum' => 'decimal:4', 'Harga' => 'decimal:2'];
    }
}
