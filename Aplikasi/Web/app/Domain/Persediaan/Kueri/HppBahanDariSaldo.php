<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use App\Domain\Persediaan\Model\SaldoStok;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Implementasi `PenyediaHppBahan` dari `SaldoStok` (BR-03.5, DesainF05a C.8), diikat di `PenyediaPersediaan`:
 *
 * - `idGudang` diisi → `SaldoStok.HppRataRata` lokasi itu, atau null.
 * - `idGudang` null (rata-rata tenant) → Σ NilaiPersediaan ÷ Σ JumlahTersedia (skala 6, HalfUp) atas baris ber-stok
 *   positif yang HPP-nya diketahui; bila tidak ada, `HppRataRata` bukan null yang terakhir berubah; selain itu null.
 *
 * Baris yang HPP-nya belum diketahui (`HppRataRata` null, nilai 0) tidak ikut dirata-rata supaya tidak menurunkan
 * estimasi HPP resep menjadi 0.
 */
final class HppBahanDariSaldo implements PenyediaHppBahan
{
    public function AmbilHppSatuan(int $idProduk, ?int $idGudang): ?BigDecimal
    {
        if ($idGudang !== null) {
            $hpp = SaldoStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->value('HppRataRata');

            return is_string($hpp) ? BigDecimal::of($hpp)->toScale(6, RoundingMode::HalfUp) : null;
        }

        $total = SaldoStok::query()
            ->where('IdProduk', $idProduk)
            ->where('JumlahTersedia', '>', 0)
            ->whereNotNull('HppRataRata')
            ->toBase()
            ->selectRaw('SUM(NilaiPersediaan) AS Nilai, SUM(JumlahTersedia) AS Jumlah')
            ->first();
        $jumlah = is_object($total) && is_string($total->Jumlah ?? null) ? BigDecimal::of($total->Jumlah) : BigDecimal::zero();

        if ($jumlah->isPositive() && is_object($total) && is_string($total->Nilai ?? null)) {
            return BigDecimal::of($total->Nilai)->dividedBy($jumlah, 6, RoundingMode::HalfUp);
        }

        $terakhir = SaldoStok::query()
            ->where('IdProduk', $idProduk)
            ->whereNotNull('HppRataRata')
            ->orderByDesc('DiubahPada')
            ->orderByDesc('Id')
            ->value('HppRataRata');

        return is_string($terakhir) ? BigDecimal::of($terakhir)->toScale(6, RoundingMode::HalfUp) : null;
    }
}
