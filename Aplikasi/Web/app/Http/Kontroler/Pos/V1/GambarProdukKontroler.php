<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Model\Produk;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /api/pos/v1/katalog/gambar/{produk}?ukuran=kecil|besar&versi=` (F-03 D.3): berkas gambar produk tenant
 * perangkat. `versi` hanya untuk cache (nama berkas berversi). Tanpa gambar atau produk tenant lain = 404.
 */
final class GambarProdukKontroler extends Kontroler
{
    public function Unduh(string $produk, Request $permintaan, PenyimpanGambarProduk $penyimpan): StreamedResponse
    {
        $baris = Produk::query()->where('Uuid', $produk)->firstOrFail();

        return $penyimpan->Unduh($baris, $permintaan->query('ukuran') === 'kecil' ? 'kecil' : 'besar');
    }
}
