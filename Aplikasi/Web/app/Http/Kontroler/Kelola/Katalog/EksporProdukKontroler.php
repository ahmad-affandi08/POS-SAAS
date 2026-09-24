<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Impor\Layanan\PenulisEksporProduk;
use App\Http\Permintaan\Kelola\Katalog\EksporProdukPermintaan;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor daftar produk F-03 (E.2) ke Excel/CSV dengan saringan aktif; hasilnya bisa diimpor kembali (templat Umum).
 */
final class EksporProdukKontroler extends DasarKatalogKontroler
{
    public function Ekspor(EksporProdukPermintaan $permintaan, PenulisEksporProduk $penulis): StreamedResponse
    {
        return $penulis->Alirkan($permintaan->AmbilSaring(), $permintaan->AmbilFormat());
    }
}
