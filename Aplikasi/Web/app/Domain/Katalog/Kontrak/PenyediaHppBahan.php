<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kontrak;

use Brick\Math\BigDecimal;

/**
 * Sumber HPP per satuan dasar bahan untuk estimasi HPP resep (BR-03.5). Sebelum F-05a diikat ke implementasi yang
 * selalu mengembalikan null ("HPP belum tersedia"); F-05a mengikat ulang ke HPP rata-rata bergerak.
 */
interface PenyediaHppBahan
{
    /** HPP per satuan dasar, skala 6; null = belum tersedia. `idGudang` null = rata-rata tenant. */
    public function AmbilHppSatuan(int $idProduk, ?int $idGudang): ?BigDecimal;
}
