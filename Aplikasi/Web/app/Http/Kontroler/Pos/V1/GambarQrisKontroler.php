<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Penjualan\Kueri\DaftarMetodePembayaran;
use App\Domain\Penjualan\Layanan\PenyimpanGambarQris;
use App\Http\Kontroler\Kontroler;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /api/pos/v1/metode-pembayaran/{metodePembayaran}/gambar-qris` (F-07b): gambar QRIS statis metode pembayaran
 * tenant perangkat untuk layar Bayar (disimpan offline di perangkat). Metode tenant lain, bukan QRIS statis, atau tanpa
 * gambar = 404.
 */
final class GambarQrisKontroler extends Kontroler
{
    public function Unduh(string $metodePembayaran, DaftarMetodePembayaran $daftar, PenyimpanGambarQris $penyimpan): StreamedResponse
    {
        $path = $daftar->CariPathGambarQrisPos($metodePembayaran);
        abort_if($path === null, 404);

        return $penyimpan->Unduh($path);
    }
}
