<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Persediaan\Model\ImporStokAwal;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Menyimpan pemetaan kolom + lokasi & tanggal bawaan, lalu memvalidasi (DesainF05a C.7).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class SimpanPemetaanImporStokAwal
{
    /**
     * @param  array<string, int|null>  $pemetaan  kunci = BidangImporStokAwal
     */
    public function Jalankan(ImporStokAwal $impor, array $pemetaan, ?int $idGudangBawaan, CarbonImmutable $tanggal): ImporStokAwal
    {
        throw new LogicException('F-05a Tim E');
    }
}
