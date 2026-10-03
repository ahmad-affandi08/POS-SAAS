<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pembelian;

use App\Domain\Pembelian\Kueri\CariProdukPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian produk + satuan pembelian untuk formulir pembelian (`GET /kelola/pembelian/produk/cari?kata=&gudang=&pemasok=`).
 * `pemasok` (opsional) mengutamakan harga beli terakhir dari pemasok itu.
 */
final class ProdukPembelianKontroler extends DasarPembelianKontroler
{
    public function Cari(Request $permintaan, CariProdukPembelian $cari): JsonResponse
    {
        $uuidGudang = $permintaan->query('gudang');
        $idGudang = is_string($uuidGudang) && $uuidGudang !== '' ? $this->CariGudangBoleh($uuidGudang)->id : null;

        $uuidPemasok = $permintaan->query('pemasok');
        $idPemasok = is_string($uuidPemasok) && $uuidPemasok !== ''
            ? Pemasok::query()->where('Uuid', $uuidPemasok)->value('Id')
            : null;

        return response()->json(['Data' => $cari->Cari(
            (string) $permintaan->query('kata', ''),
            $idGudang,
            (int) $permintaan->query('batas', '20'),
            is_numeric($idPemasok) ? (int) $idPemasok : null,
        )]);
    }
}
