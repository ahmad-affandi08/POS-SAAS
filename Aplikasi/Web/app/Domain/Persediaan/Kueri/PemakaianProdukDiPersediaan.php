<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use LogicException;

/**
 * Implementasi `PemeriksaPemakaianProduk`: produk punya riwayat stok atau ada di draf stok awal (DesainF05a C.8). Belum ditandai: Tim F mendaftarkan tag-nya di PenyediaPersediaan.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PemakaianProdukDiPersediaan implements PemeriksaPemakaianProduk
{
    public function PeriksaPemakaian(int $idProduk): ?string
    {
        throw new LogicException('F-05a Tim F');
    }
}
