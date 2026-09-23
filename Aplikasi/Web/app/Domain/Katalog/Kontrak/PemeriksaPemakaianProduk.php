<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kontrak;

/**
 * Titik perluasan BR-03.2 "produk sudah dipakai". Setiap domain yang memakai produk (varian, resep, pilihan, paket,
 * lalu F-05/F-07) menandai implementasinya dengan tag `TAG` di provider-nya. Produk yang dipakai tidak bisa dihapus
 * (hanya diarsipkan) dan jenisnya tidak bisa diubah.
 */
interface PemeriksaPemakaianProduk
{
    public const TAG = 'katalog.pemeriksa-pemakaian-produk';

    /**
     * Alasan singkat Bahasa Indonesia bila produk dipakai (misal "dipakai sebagai bahan di resep Es Kopi versi 3"),
     * atau null. Dipanggil di dalam transaksi Aksi.
     */
    public function PeriksaPemakaian(int $idProduk): ?string;
}
