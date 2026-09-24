<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use LogicException;

/**
 * Aritmetika HPP tanpa float (DesainF05a C.3): Nilai(q, c) = (|q| × c) skala 2 HalfUp; Hpp(V, q) = (V ÷ |q|) skala 6 HalfUp.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class AritmetikaHpp
{
    public static function Nilai(Kuantitas $jumlah, BigDecimal $hppSatuan): Uang
    {
        throw new LogicException('F-05a Tim A');
    }

    public static function Hpp(Uang $nilai, Kuantitas $jumlah): BigDecimal
    {
        throw new LogicException('F-05a Tim A');
    }
}
