<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Persediaan\Kueri\BatchProdukPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * K-19 (F-05g di POS): `GET /api/pos/v1/produk/{uuid}/batch` mengembalikan batch bersisa produk di lokasi stok Toko
 * outlet perangkat, urut FEFO, beserta sisa hari sebelum kedaluwarsa. Hanya baca; server tetap memilih batch saat
 * penjualan disinkronkan. Produk tak dikenal = 404.
 */
final class BatchProdukKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, string $produk, BatchProdukPos $kueri, TanggalBisnisOutlet $tanggal): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hasil = $kueri->Ambil($perangkat->IdOutlet, strtoupper($produk), $tanggal->Hitung($perangkat->IdOutlet));
        abort_if($hasil === null, 404);

        return response()->json($hasil);
    }
}
