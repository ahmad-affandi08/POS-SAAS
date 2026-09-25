<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pembelian;

use App\Domain\Pembelian\Kueri\CariProdukPembelian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian produk + satuan pembelian untuk formulir pembelian (`GET /kelola/pembelian/produk/cari?kata=&gudang=`). */
final class ProdukPembelianKontroler extends DasarPembelianKontroler
{
    public function Cari(Request $permintaan, CariProdukPembelian $cari): JsonResponse
    {
        $uuidGudang = $permintaan->query('gudang');
        $idGudang = is_string($uuidGudang) && $uuidGudang !== '' ? $this->CariGudangBoleh($uuidGudang)->id : null;

        return response()->json(['Data' => $cari->Cari((string) $permintaan->query('kata', ''), $idGudang, (int) $permintaan->query('batas', '20'))]);
    }
}
