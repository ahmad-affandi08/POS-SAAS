<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use LogicException;

/**
 * Memeriksa konsistensi rantai SaldoSetelah/NilaiSetelah, lapisan FIFO, dan status seri tenant aktif (DesainF05a C.9).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim H (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PemeriksaKonsistensiStok
{
    /**
     * @return list<string> uraian perbedaan; kosong = konsisten
     */
    public function Periksa(): array
    {
        throw new LogicException('F-05a Tim H');
    }
}
