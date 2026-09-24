<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use LogicException;

/**
 * Baris MutasiStok satu dokumen sumber, opsional hanya KunciBaris berawalan tertentu (misal `P/`).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class MutasiDokumen
{
    /**
     * @return list<MutasiStok>
     */
    public function Ambil(JenisReferensiMutasi $jenis, int $idReferensi, ?string $awalanKunci = null): array
    {
        throw new LogicException('F-05a Tim A');
    }
}
