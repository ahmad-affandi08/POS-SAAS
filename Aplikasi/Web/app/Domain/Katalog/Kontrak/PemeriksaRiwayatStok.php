<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kontrak;

/**
 * Titik perluasan "produk sudah punya riwayat stok" (DesainF05a C.4). Diikat F-05a (`PenyediaPersediaan`) ke
 * implementasi di domain Persediaan. Katalog memakainya untuk mengunci `Pelacakan` (Tidak/Batch/Seri) begitu produk
 * pernah bermutasi stok (`PelacakanTerkunci`).
 */
interface PemeriksaRiwayatStok
{
    /** True bila produk sudah punya baris `MutasiStok`. Dipanggil di dalam transaksi Aksi. */
    public function CekPunyaRiwayatStok(int $idProduk): bool;
}
