<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\CariProduk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pemilih produk F-03 (D.2): `GET /kelola/produk/cari?kata=&jenis[]=&batas=` → `{ "Data": [...] }`.
 */
final class CariProdukKontroler extends DasarKatalogKontroler
{
    public function Cari(Request $permintaan, CariProduk $cari): JsonResponse
    {
        $jenis = array_values(array_filter(array_map(
            fn (mixed $nilai): ?JenisProduk => is_string($nilai) ? JenisProduk::tryFrom($nilai) : null,
            (array) $permintaan->query('jenis', []),
        )));

        return response()->json([
            'Data' => $cari->Cari(mb_substr(trim($permintaan->string('kata')->toString()), 0, 100), $jenis, $permintaan->integer('batas', 20)),
        ]);
    }
}
