<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kontrak;

/**
 * Titik perluasan F-13a "akun dipakai": domain lain yang menyimpan rujukan akun (kategori kas F-06, metode
 * pembayaran F-07) menandai implementasinya dengan tag `TAG` di provider-nya. Akun yang dipakai tidak bisa dihapus,
 * hanya dinonaktifkan. Akuntansi tidak membaca tabel domain lain (§13.3).
 */
interface PemeriksaPemakaianAkun
{
    public const TAG = 'akuntansi.pemeriksa-pemakaian-akun';

    /** Alasan singkat Bahasa Indonesia bila akun dipakai (misal "dipakai kategori kas Beli es batu"), atau null. */
    public function PeriksaPemakaian(int $idAkun): ?string;
}
