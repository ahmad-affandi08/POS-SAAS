<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use LogicException;

/**
 * Baris jurnal J-05.1 stok awal per outlet (Dr Persediaan, Cr EkuitasSaldoAwal, selisih HPP) (DesainF05a C.6.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PenyusunJurnalStokAwal
{
    /**
     * @param  array<int, DataInfoProdukStok>  $produk  kunci = IdProduk
     * @return list<DataBarisJurnal>
     */
    public function Susun(HasilCatatMutasi $hasil, array $produk, DataInfoGudang $gudang): array
    {
        throw new LogicException('F-05a Tim C');
    }
}
