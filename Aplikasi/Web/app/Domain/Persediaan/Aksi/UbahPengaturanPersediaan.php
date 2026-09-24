<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Enum\MetodeHpp;
use LogicException;

/**
 * Mengubah MetodeHpp & StokBolehMinus tenant di bawah kunci X Tenant (`MetodeHppTerkunci`, DesainF05a C.8).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class UbahPengaturanPersediaan
{
    public function Jalankan(MetodeHpp $metode, bool $bolehMinus): void
    {
        throw new LogicException('F-05a Tim F');
    }
}
