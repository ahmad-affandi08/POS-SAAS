<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Pelanggan\Kueri\DaftarPilihanPelanggan;
use App\Domain\Penjualan\Kueri\CariProdukGrosir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian untuk formulir SO grosir: produk + satuan jual (`GET /kelola/grosir/produk/cari?kata=&gudang=`) dan
 * pelanggan beserta limit kreditnya untuk dropdown (`GET /kelola/grosir/pelanggan/cari?kata=`; kata kosong = daftar awal).
 */
final class ProdukGrosirKontroler extends DasarGrosirKontroler
{
    public function Cari(Request $permintaan, CariProdukGrosir $cari): JsonResponse
    {
        $uuidGudang = $permintaan->query('gudang');
        $idGudang = is_string($uuidGudang) && $uuidGudang !== '' ? $this->CariGudangBoleh($uuidGudang)->id : null;

        return response()->json(['Data' => $cari->Cari((string) $permintaan->query('kata', ''), $idGudang, (int) $permintaan->query('batas', '20'))]);
    }

    /** Limit kredit & piutang pelanggan ikut terbaca di sini, supaya operator tahu sebelum menyusun SO besar. */
    public function CariPelanggan(Request $permintaan, DaftarPilihanPelanggan $cari): JsonResponse
    {
        return response()->json(['Data' => $cari->Cari((string) $permintaan->query('kata', ''))]);
    }
}
