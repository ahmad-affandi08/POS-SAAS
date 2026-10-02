<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Model\SaldoStok;

/**
 * Jumlah tersedia per produk di satu lokasi stok tenant aktif (Modul Salesman bagian 1: stok lokasi Toko outlet untuk
 * disimpan HP salesman, §9.7 "lihat stok"). Hanya jumlah, tanpa nilai/HPP. Pemetaan Id produk ke Uuid oleh pemanggil
 * lewat kueri publik Katalog.
 */
final class StokTersediaGudang
{
    /**
     * @return array<int, string> Id produk → JumlahTersedia (string desimal)
     */
    public function Ambil(int $idGudang): array
    {
        $hasil = [];

        foreach (SaldoStok::query()->where('IdGudang', $idGudang)->orderBy('IdProduk')->get(['IdProduk', 'JumlahTersedia']) as $s) {
            $hasil[$s->IdProduk] = (string) $s->JumlahTersedia;
        }

        return $hasil;
    }

    /**
     * Bengkel (§9.10): jumlah tersedia sebagian produk saja (sparepart di perintah kerja), tanpa memuat seluruh lokasi.
     *
     * @param  list<int>  $idProduk
     * @return array<int, string> Id produk → JumlahTersedia (string desimal); produk tanpa saldo tidak muncul
     */
    public function AmbilProduk(int $idGudang, array $idProduk): array
    {
        $idProduk = array_values(array_unique($idProduk));

        if ($idProduk === []) {
            return [];
        }

        $hasil = [];

        foreach (SaldoStok::query()->where('IdGudang', $idGudang)->whereIn('IdProduk', $idProduk)->get(['IdProduk', 'JumlahTersedia']) as $s) {
            $hasil[$s->IdProduk] = (string) $s->JumlahTersedia;
        }

        return $hasil;
    }
}
