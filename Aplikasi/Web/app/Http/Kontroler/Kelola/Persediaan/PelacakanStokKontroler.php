<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Persediaan\Kueri\PelacakanTersedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Batch bersisa & nomor seri tersedia satu produk di satu lokasi stok (F-05b, pilihan baris keluar transfer &
 * penyesuaian): `GET /kelola/persediaan/pelacakan?produk={uuid}&gudang={uuid}` → `{Batch: [...], Seri: [...]}`.
 * Lokasi di luar outlet pelaku atau produk/lokasi tenant lain = 404.
 */
final class PelacakanStokKontroler extends DasarDokumenPersediaanKontroler
{
    public function Tampilkan(Request $permintaan, PelacakanTersedia $pelacakan): JsonResponse
    {
        $uuidProduk = $permintaan->string('produk')->toString();
        $gudang = $this->CariGudangBoleh($permintaan->string('gudang')->toString());
        $produk = app(InfoProdukStok::class)->AmbilDariUuid([$uuidProduk])[$uuidProduk] ?? null;
        abort_if($produk === null, 404);

        return response()->json($pelacakan->Ambil($produk->id, $gudang->id));
    }
}
