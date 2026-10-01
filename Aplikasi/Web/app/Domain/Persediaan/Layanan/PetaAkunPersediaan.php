<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Katalog\Enum\JenisProduk;

/**
 * Peran akun persediaan per jenis produk (J-05.1, DesainF05a C.6.4, H-15): BahanBaku → PersediaanBahanBaku, jenis
 * berstok lain (Stok, Produksi) → PersediaanBarangDagang. `PersediaanBarangJadi` untuk Produksi menunggu J-05.6
 * (F-05e). Konsinyasi → `HutangKonsinyasi` (F-05i, J-05.7): barang titipan bukan aset toko, jadi "persediaan" yang
 * berkurang saat terjual adalah hutang ke penitip — jurnal penjualan menjadi Dr HPP / Cr Hutang Konsinyasi, dan retur
 * penjualan/void membaliknya, tanpa kode khusus di Penjualan.
 */
final class PetaAkunPersediaan
{
    public function UntukJenis(JenisProduk $jenis): PeranAkun
    {
        return match ($jenis) {
            JenisProduk::BahanBaku => PeranAkun::PersediaanBahanBaku,
            JenisProduk::Konsinyasi => PeranAkun::HutangKonsinyasi,
            default => PeranAkun::PersediaanBarangDagang,
        };
    }
}
