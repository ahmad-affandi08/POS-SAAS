<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kontrak;

use App\Domain\Katalog\Data\KonteksKatalogPos;

/**
 * Penyusun bagian payload katalog POS (`GET /api/pos/v1/katalog`, F-03 D.3). Setiap tim menandai implementasinya
 * dengan tag `TAG` di provider-nya; `KatalogPos` menggabungkan semua bagian.
 */
interface BagianKatalogPos
{
    public const TAG = 'katalog.bagian-pos';

    /**
     * Baris per bagian. Kunci = nama bagian ("Produk", "Pilihan", ...); nilai = daftar baris dengan kunci kolom
     * (PascalCase), uang dan jumlah sebagai string, FK sebagai `Uuid{Tabel}`, waktu ISO UTC.
     * Tanpa `sejak` = semua baris yang belum dihapus; dengan `sejak` = baris dengan `DiubahPada >= sejak`.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function AmbilBagian(KonteksKatalogPos $konteks): array;
}
