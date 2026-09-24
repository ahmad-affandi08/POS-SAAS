<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Katalog\Enum\JenisProduk;

/**
 * Peran akun persediaan per jenis produk (J-05.1, DesainF05a C.6.4, H-15): BahanBaku → PersediaanBahanBaku, jenis
 * berstok lain (Stok, Produksi) → PersediaanBarangDagang. `PersediaanBarangJadi` untuk Produksi menunggu J-05.6
 * (F-05e).
 */
final class PetaAkunPersediaan
{
    public function UntukJenis(JenisProduk $jenis): PeranAkun
    {
        return $jenis === JenisProduk::BahanBaku ? PeranAkun::PersediaanBahanBaku : PeranAkun::PersediaanBarangDagang;
    }
}
