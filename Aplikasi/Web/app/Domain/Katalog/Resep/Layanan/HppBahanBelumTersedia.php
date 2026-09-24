<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Layanan;

use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use Brick\Math\BigDecimal;

/**
 * Ikatan bawaan `PenyediaHppBahan` sebelum F-05a (H1): HPP bahan belum ada karena stok awal dan HPP rata-rata
 * bergerak belum diisi, sehingga estimasi HPP resep tampil "HPP belum tersedia". F-05a mengikat ulang kontrak ini.
 */
final class HppBahanBelumTersedia implements PenyediaHppBahan
{
    public function AmbilHppSatuan(int $idProduk, ?int $idGudang): ?BigDecimal
    {
        return null;
    }
}
