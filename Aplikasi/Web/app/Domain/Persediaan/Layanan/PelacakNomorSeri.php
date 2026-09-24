<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\NomorSeri;
use LogicException;

/**
 * Kunci & pembaruan NomorSeri (kunci L5) untuk mutasi produk ber-pelacakan Seri (DesainF05a C.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim D (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PelacakNomorSeri
{
    public function KunciMasuk(int $idProduk, int $idGudang, string $nomor): NomorSeri
    {
        throw new LogicException('F-05a Tim D');
    }

    public function KunciKeluar(int $idNomorSeri, int $idProduk, int $idGudang): NomorSeri
    {
        throw new LogicException('F-05a Tim D');
    }

    public function TandaiMasuk(NomorSeri $seri, int $idGudang): void
    {
        throw new LogicException('F-05a Tim D');
    }

    public function TandaiKeluar(NomorSeri $seri, StatusNomorSeri $status): void
    {
        throw new LogicException('F-05a Tim D');
    }
}
