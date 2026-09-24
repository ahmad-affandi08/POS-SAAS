<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use Carbon\CarbonInterface;
use LogicException;

/**
 * Menolak transaksi bertanggal di periode terkunci (`PeriodeTerkunci`, DesainF05a C.5).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PenjagaKunciPeriode
{
    public function PastikanTerbuka(CarbonInterface $tanggal): void
    {
        throw new LogicException('F-05a Tim B');
    }

    /**
     * @param  string  $periode  `YYYY-MM`
     */
    public function CekTerkunci(string $periode): bool
    {
        throw new LogicException('F-05a Tim B');
    }
}
