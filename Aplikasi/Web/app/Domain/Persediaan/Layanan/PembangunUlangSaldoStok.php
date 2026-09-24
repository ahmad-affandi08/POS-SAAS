<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use LogicException;

/**
 * Membangun ulang SaldoStok (dan BatchStok.JumlahSisa) tenant aktif dari MutasiStok, per pasangan dalam transaksi sendiri (DesainF05a C.9).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim H (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PembangunUlangSaldoStok
{
    /**
     * @return int jumlah pasangan (produk, lokasi stok) yang diperbaiki
     */
    public function Jalankan(): int
    {
        throw new LogicException('F-05a Tim H');
    }
}
