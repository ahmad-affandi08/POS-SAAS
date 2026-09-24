<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Kueri;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;

/**
 * Kelompok pilihan yang terpasang dan yang masih tersedia untuk satu produk (tipe FE `PropsPilihanProduk` tanpa
 * `Kepala` dan `Izin`, F-03 E.9). Anak varian menampilkan kelompok induknya (`DariInduk`, hanya-baca).
 */
final class PilihanProduk
{
    /**
     * @return array{Terpasang: list<array{Uuid: string, Nama: string, Ringkasan: string}>, Tersedia: list<array{Uuid: string, Nama: string, Ringkasan: string}>, DariInduk: bool}
     */
    public function Ambil(Produk $produk): array
    {
        $dariInduk = $produk->IdInduk !== null;
        $idPemilik = $produk->IdInduk ?? $produk->Id;
        $idTerpasang = ProdukKelompokPilihan::query()->where('IdProduk', $idPemilik)->orderBy('Urutan')->pluck('IdKelompokPilihan')->all();
        $kelompok = KelompokPilihan::query()->orderBy('Urutan')->orderBy('Nama')->get()->keyBy('Id');
        $jumlahPilihan = Pilihan::query()->where('Aktif', true)->selectRaw('IdKelompokPilihan, COUNT(*) AS Jumlah')->groupBy('IdKelompokPilihan')->pluck('Jumlah', 'IdKelompokPilihan');
        $susun = fn (KelompokPilihan $baris): array => [
            'Uuid' => $baris->Uuid,
            'Nama' => $baris->Nama,
            'Ringkasan' => DaftarKelompokPilihan::BuatRingkasan($baris, (int) $jumlahPilihan->get($baris->Id, 0)),
        ];

        $terpasang = [];

        foreach ($idTerpasang as $id) {
            $baris = $kelompok->get($id);

            if ($baris instanceof KelompokPilihan) {
                $terpasang[] = $susun($baris);
            }
        }

        return [
            'Terpasang' => $terpasang,
            'Tersedia' => $dariInduk ? [] : array_values($kelompok->except($idTerpasang)->map($susun)->all()),
            'DariInduk' => $dariInduk,
        ];
    }
}
