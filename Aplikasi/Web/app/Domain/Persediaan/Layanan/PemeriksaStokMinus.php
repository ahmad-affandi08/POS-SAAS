<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Tenant\Data\DataPengaturanPersediaan;
use LogicException;

/**
 * Aturan stok minus BR-05.2 (DesainF05a C.4): boleh hanya bila Pelacakan = Tidak dan (Produk.BolehMinus ?? Tenant.StokBolehMinus).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PemeriksaStokMinus
{
    public function CekBolehMinus(DataInfoProdukStok $produk, DataPengaturanPersediaan $pengaturan): bool
    {
        throw new LogicException('F-05a Tim A');
    }

    public function Pastikan(DataInfoProdukStok $produk, DataInfoGudang $gudang, DataPengaturanPersediaan $pengaturan, Kuantitas $tersedia, Kuantitas $diminta): void
    {
        throw new LogicException('F-05a Tim A');
    }
}
