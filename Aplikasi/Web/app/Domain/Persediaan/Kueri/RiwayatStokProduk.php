<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PemeriksaRiwayatStok;
use App\Domain\Persediaan\Model\MutasiStok;

/**
 * Implementasi `PemeriksaRiwayatStok` (DesainF05a C.4): produk sudah punya baris `MutasiStok` di tenant aktif.
 * Dipakai Katalog untuk mengunci `Pelacakan` (`PelacakanTerkunci`). Dibaca dengan kunci baca (`sharedLock`) karena
 * dipanggil di dalam transaksi Aksi Katalog: pembacaan biasa di REPEATABLE READ bisa memakai snapshot lama, dan kunci
 * baca menahan mutasi baru produk ini sampai perubahan produk selesai.
 */
final class RiwayatStokProduk implements PemeriksaRiwayatStok
{
    public function CekPunyaRiwayatStok(int $idProduk): bool
    {
        return MutasiStok::query()->where('IdProduk', $idProduk)->sharedLock()->exists();
    }
}
