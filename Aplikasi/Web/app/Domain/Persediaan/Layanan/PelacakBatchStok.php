<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Model\BatchStok;
use LogicException;

/**
 * Kunci & pembaruan BatchStok (kunci L4) untuk mutasi produk ber-pelacakan Batch (DesainF05a C.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim D (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PelacakBatchStok
{
    public function KunciMasuk(int $idProduk, int $idGudang, DataBatchMasuk $batch): BatchStok
    {
        throw new LogicException('F-05a Tim D');
    }

    public function KunciKeluar(int $idBatchStok, int $idProduk, int $idGudang): BatchStok
    {
        throw new LogicException('F-05a Tim D');
    }

    public function Terapkan(BatchStok $batch, Kuantitas $delta): void
    {
        throw new LogicException('F-05a Tim D');
    }
}
