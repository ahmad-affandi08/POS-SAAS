<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PemeriksaRiwayatStok;
use LogicException;

/**
 * Implementasi `PemeriksaRiwayatStok`: produk sudah punya MutasiStok (DesainF05a C.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim D (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class RiwayatStokProduk implements PemeriksaRiwayatStok
{
    public function CekPunyaRiwayatStok(int $idProduk): bool
    {
        throw new LogicException('F-05a Tim D');
    }
}
