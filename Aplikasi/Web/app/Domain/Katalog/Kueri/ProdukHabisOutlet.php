<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHabis;

/**
 * F-17 BR-17.2: Uuid produk yang ditandai habis di satu outlet (untuk aplikasi POS/KDS). Produk yang sudah
 * diarsipkan atau dihapus tidak ikut.
 */
final class ProdukHabisOutlet
{
    /**
     * @return list<string>
     */
    public function AmbilUuid(int $idOutlet): array
    {
        return array_values(Produk::query()
            ->whereIn('Id', ProdukHabis::query()->where('IdOutlet', $idOutlet)->select('IdProduk'))
            ->orderBy('Uuid')
            ->pluck('Uuid')
            ->all());
    }
}
