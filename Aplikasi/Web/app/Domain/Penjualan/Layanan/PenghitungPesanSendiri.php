<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\MenuPesanSendiri;

/**
 * F-17: harga baris pesanan tamu dihitung ulang server dari katalog (harga kanal `MakanDiTempat` + harga pilihan);
 * harga dari peramban tidak pernah dipakai. Total baris = (HargaSatuan + HargaPilihan) × Jumlah, Subtotal = Σ Total.
 * Pajak, biaya layanan, promo, dan pembulatan dihitung kasir saat pesanan meja dibayar (mesin kalkulasi F-07a
 * membutuhkan snapshot pajak & promo perangkat), sehingga halaman tamu hanya menampilkan subtotal.
 */
final class PenghitungPesanSendiri
{
    public const CATATAN = 'Pajak & biaya layanan dihitung di kasir.';

    public function __construct(private readonly MenuPesanSendiri $menu) {}

    /**
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>}>  $baris
     * @return array{Baris: list<array{UuidProduk: string, UuidProdukSatuan: string, NamaProduk: string, Jumlah: Kuantitas, HargaSatuan: Uang, HargaPilihan: Uang, Total: Uang, Pilihan: list<array{UuidPilihan: string, Nama: string, Harga: string}>}>, Subtotal: Uang}
     */
    public function Hitung(int $idOutlet, array $baris): array
    {
        $berharga = $this->menu->HitungBaris($idOutlet, $baris);
        $hasil = [];
        $subtotal = Uang::Nol();

        foreach ($berharga as $i => $b) {
            $jumlah = Kuantitas::Dari($baris[$i]['Jumlah']);
            $total = $b['HargaSatuan']->Tambah($b['HargaPilihan'])->Kali($jumlah->KeDesimal());
            $subtotal = $subtotal->Tambah($total);
            $hasil[] = [...$b, 'Jumlah' => $jumlah, 'Total' => $total];
        }

        return ['Baris' => $hasil, 'Subtotal' => $subtotal];
    }
}
