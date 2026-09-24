<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Persediaan\Model\SaldoStok;
use LogicException;

/**
 * Mengunci baris SaldoStok (kunci L3) urut (IdProduk, IdGudang): upsert baris nol lalu FOR UPDATE. Reentran.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PengunciSaldoStok
{
    /**
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, SaldoStok> kunci = SaldoStok::BuatKunciPasangan()
     */
    public function Kunci(array $pasangan): array
    {
        throw new LogicException('F-05a Tim A');
    }
}
