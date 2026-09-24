<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Katalog\Enum\JenisProduk;
use LogicException;

/**
 * Peran akun persediaan per jenis produk: BahanBaku → PersediaanBahanBaku, lainnya → PersediaanBarangDagang (DesainF05a C.6.4, H-15).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PetaAkunPersediaan
{
    public function UntukJenis(JenisProduk $jenis): PeranAkun
    {
        throw new LogicException('F-05a Tim C');
    }
}
