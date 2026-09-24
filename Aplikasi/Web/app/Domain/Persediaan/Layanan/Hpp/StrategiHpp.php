<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;

/**
 * Strategi penilaian HPP per metode (DesainF05a C.3): rata-rata bergerak atau FIFO. Murni (tanpa database):
 * mesin buku stok memuat `KeadaanHpp` satu (produk, lokasi stok) di bawah kunci, menerapkan baris demi baris dalam
 * urutan masukan, lalu menyimpan keadaan akhirnya. `Terapkan()` mengubah `keadaan` di tempat.
 */
interface StrategiHpp
{
    /**
     * Menilai satu mutasi (jumlah bertanda) dan memperbarui keadaan: Q′ = Q + q, N′ = N + TotalHpp, A′, lapisan.
     *
     * @throws PelanggaranAturanBisnis `LapisanSudahTerpakai` (FIFO, pembalik lapisan tertentu)
     */
    public function Terapkan(KeadaanHpp $keadaan, MasukanHpp $masukan): HasilHpp;
}
