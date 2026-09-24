<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;

/**
 * Satu-satunya titik Aksi Tim 1 menyentuh harga (DesainF03 G.2): harga awal satuan baru, harga dasar anak varian,
 * dan penghapusan harga satuan yang dibuang. Sumber = nilai `SumberPerubahanHarga` Tim 2 (Manual, Impor,
 * PanduanAwal, Varian, Sistem). Dipanggil di dalam transaksi Aksi pemanggil.
 *
 * SEMENTARA sampai T2-A (`Harga\Aksi\SimpanHargaProduk`, `HapusHargaSatuanProduk`) digabung: baris harga dasar
 * ditulis langsung tanpa `RiwayatHarga`.
 */
final class PenghubungHargaProduk
{
    /**
     * @param  array<int, list<DataBarisHarga>>  $perSatuan  kunci = `ProdukSatuan.Id`
     */
    public function SimpanHargaDasar(Produk $produk, array $perSatuan, string $sumber): void
    {
        unset($sumber);

        foreach ($perSatuan as $idProdukSatuan => $baris) {
            ProdukHarga::query()->where('IdProdukSatuan', $idProdukSatuan)->whereNull('IdDaftarHarga')->delete();

            foreach ($baris as $harga) {
                ProdukHarga::query()->create([
                    'IdProduk' => $produk->Id,
                    'IdProdukSatuan' => $idProdukSatuan,
                    'IdDaftarHarga' => null,
                    'JumlahMinimum' => $harga->jumlahMinimum->KeString(),
                    'Harga' => $harga->harga->KeString(),
                ]);
            }
        }
    }

    public function HapusHargaSatuan(ProdukSatuan $satuan, string $sumber): void
    {
        unset($sumber);
        ProdukHarga::query()->where('IdProdukSatuan', $satuan->Id)->delete();
    }
}
