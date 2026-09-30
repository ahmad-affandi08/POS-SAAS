<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\NomorSeri;

/**
 * Kueri publik domain Persediaan untuk penjualan produk bernomor seri (F-05h): mencari nomor yang tersedia di lokasi
 * stok untuk dijual, dan membaca nomor dari `IdNomorSeri` untuk membalik mutasi keluar (void & retur) yang menyebut
 * nomor seri masuk lewat teks, bukan Id. Dibaca tanpa kunci; yang mengunci & memvalidasi tetap `PelacakNomorSeri`.
 */
final class InfoNomorSeri
{
    /**
     * @param  list<string>  $nomor
     * @return array<string, int> NOMOR HURUF BESAR → IdNomorSeri, hanya yang `Tersedia` di `$idGudang` (pencocokan tidak membedakan huruf besar/kecil)
     */
    public function CariTersedia(int $idProduk, int $idGudang, array $nomor, bool $bacaTerbaru = false): array
    {
        if ($nomor === []) {
            return [];
        }

        $hasil = [];

        $kueri = NomorSeri::query();

        if ($bacaTerbaru) {
            // Membaca data terkini (bukan snapshot transaksi) saat memilih ulang setelah nomor keburu diambil penjualan lain.
            $kueri->sharedLock();
        }

        foreach ($kueri
            ->where('IdProduk', $idProduk)
            ->where('IdGudang', $idGudang)
            ->where('Status', StatusNomorSeri::Tersedia->value)
            ->whereIn('Nomor', $nomor)
            ->get(['Id', 'Nomor']) as $s) {
            $hasil[mb_strtoupper($s->Nomor)] = $s->Id;
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $idNomorSeri
     * @return array<int, string> IdNomorSeri → nomor
     */
    public function AmbilNomor(array $idNomorSeri): array
    {
        $idNomorSeri = array_values(array_unique($idNomorSeri));

        return $idNomorSeri === [] ? [] : NomorSeri::query()->whereIn('Id', $idNomorSeri)->pluck('Nomor', 'Id')->all();
    }

    /**
     * Nomor seri yang masih `Terjual` dan tertaut ke baris penjualan itu, yaitu unit yang belum diretur/di-void.
     *
     * @return array<int, string> IdNomorSeri → nomor, urut Id
     */
    public function AmbilTerjualDiBaris(int $idPenjualanDetail): array
    {
        return NomorSeri::query()
            ->where('IdPenjualanDetail', $idPenjualanDetail)
            ->where('Status', StatusNomorSeri::Terjual->value)
            ->orderBy('Id')
            ->pluck('Nomor', 'Id')
            ->all();
    }
}
