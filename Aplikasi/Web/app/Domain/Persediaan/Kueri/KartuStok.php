<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use Carbon\CarbonImmutable;
use LogicException;

/**
 * Kartu stok satu (produk, lokasi stok) urut Id dengan saldo awal/akhir (tipe FE `PropsKartuStok`, DesainF05a C.8).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class KartuStok
{
    /**
     * @return array<string, mixed>
     */
    public function Ambil(int $idProduk, int $idGudang, CarbonImmutable $dari, CarbonImmutable $sampai, int $halaman): array
    {
        throw new LogicException('F-05a Tim F');
    }
}
