<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use LogicException;

/**
 * Aksi publik buku stok (BR-05.1, DesainF05a C.2): mencatat baris MutasiStok satu dokumen, menilai HPP, dan memperbarui SaldoStok di transaksi pemanggil (savepoint).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class CatatMutasiStok
{
    public function Jalankan(DataDokumenMutasi $dokumen): HasilCatatMutasi
    {
        throw new LogicException('F-05a Tim A');
    }
}
