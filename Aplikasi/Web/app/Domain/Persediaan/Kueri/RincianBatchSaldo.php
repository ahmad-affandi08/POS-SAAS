<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * Rincian batch dan jumlah nomor seri tersedia per (produk, lokasi stok) untuk halaman saldo (DesainF05a C.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim D (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class RincianBatchSaldo
{
    /**
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, list<array{NomorBatch: string, TanggalKedaluwarsa: string|null, JumlahSisa: string}>>
     */
    public function UntukPasangan(array $pasangan): array
    {
        throw new LogicException('F-05a Tim D');
    }

    /**
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, int>
     */
    public function HitungSeriTersedia(array $pasangan): array
    {
        throw new LogicException('F-05a Tim D');
    }
}
