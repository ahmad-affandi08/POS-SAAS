<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Model\SaldoStok;

/**
 * F-04 fase 1: jumlah tersedia per (produk, lokasi stok) tenant aktif untuk domain lain yang memeriksa apakah stok
 * dari dokumennya belum terpakai sebelum membatalkan (misal pembatalan penerimaan barang). `kunci` = baca dengan
 * `FOR UPDATE` urut (IdProduk, IdGudang) sesuai urutan kunci L3 buku stok; pemanggil sudah memegang L1 tenant dan
 * kunci dokumennya. Pasangan tanpa saldo = 0.
 */
final class SaldoStokPasangan
{
    /**
     * @param  list<array{0: int, 1: int}>  $pasangan  [IdProduk, IdGudang]
     * @return array<string, Kuantitas> kunci = "IdProduk:IdGudang"
     */
    public function Ambil(array $pasangan, bool $kunci = false): array
    {
        $hasil = [];

        foreach ($pasangan as [$idProduk, $idGudang]) {
            $hasil[SaldoStok::BuatKunciPasangan($idProduk, $idGudang)] = Kuantitas::Nol();
        }

        if ($hasil === []) {
            return [];
        }

        $baris = SaldoStok::query()
            ->whereIn('IdProduk', array_values(array_unique(array_column($pasangan, 0))))
            ->whereIn('IdGudang', array_values(array_unique(array_column($pasangan, 1))))
            ->orderBy('IdProduk')
            ->orderBy('IdGudang')
            ->when($kunci, fn ($kueri) => $kueri->lockForUpdate())
            ->get(['IdProduk', 'IdGudang', 'JumlahTersedia']);

        foreach ($baris as $s) {
            $k = SaldoStok::BuatKunciPasangan($s->IdProduk, $s->IdGudang);

            if (isset($hasil[$k])) {
                $hasil[$k] = Kuantitas::Dari($s->JumlahTersedia);
            }
        }

        return $hasil;
    }
}
