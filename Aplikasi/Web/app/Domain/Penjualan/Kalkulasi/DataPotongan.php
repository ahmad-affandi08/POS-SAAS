<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Satu potongan harga (diskon manual atau promo yang sudah diterapkan) untuk mesin kalkulasi F-07a: **persen**
 * (0–100, dari dasar baris/subtotal, dibulatkan setengah ke atas ke sen) atau **nominal** (Rupiah ≥ 0). Tepat satu
 * dari keduanya terisi; buat lewat `BuatPersen()` atau `BuatNominal()`.
 */
final readonly class DataPotongan
{
    private function __construct(
        public ?BigDecimal $persen,
        public ?Uang $jumlah,
    ) {}

    public static function BuatPersen(BigDecimal|int|string $persen): self
    {
        $nilai = BigDecimal::of($persen);

        if ($nilai->isNegative() || $nilai->isGreaterThan(100)) {
            throw new InvalidArgumentException("Persen potongan harus 0 sampai 100: {$nilai}");
        }

        return new self($nilai, null);
    }

    public static function BuatNominal(Uang $jumlah): self
    {
        if ($jumlah->BernilaiNegatif()) {
            throw new InvalidArgumentException("Jumlah potongan tidak boleh negatif: {$jumlah}");
        }

        return new self(null, $jumlah);
    }
}
