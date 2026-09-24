<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Harga\Aksi\HapusHargaSatuanProduk;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaProduk;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;

/**
 * Satu-satunya titik Aksi Tim 1 menyentuh harga (DesainF03 G.2): harga awal satuan baru, harga dasar anak varian,
 * dan penghapusan harga satuan yang dibuang/produk yang dihapus. Diteruskan ke Aksi Tim 2 `SimpanHargaProduk` dan
 * `HapusHargaSatuanProduk` sehingga `RiwayatHarga` (BR-03.3), jejak, dan audit harga tercatat. Dipanggil di dalam
 * transaksi Aksi pemanggil.
 */
final class PenghubungHargaProduk
{
    public function __construct(
        private readonly SimpanHargaProduk $simpanHarga,
        private readonly HapusHargaSatuanProduk $hapusHarga,
    ) {}

    /**
     * Bidang galat Tim 2 `Satuan.{i}.Harga…` (i = urutan di `$perSatuan`) dipetakan ke bidang form pemanggil lewat
     * `$bidangPerSatuan[IdProdukSatuan]`, misal `Satuan.2.HargaAwal` (form produk, akhiran `.{j}.Harga` dipertahankan) atau `HargaDasar` (varian, bidang tunggal).
     *
     * @param  array<int, list<DataBarisHarga>>  $perSatuan  kunci = `ProdukSatuan.Id`
     * @param  array<int, string>  $bidangPerSatuan
     */
    public function SimpanHargaDasar(Produk $produk, array $perSatuan, string $sumber, array $bidangPerSatuan = []): void
    {
        try {
            $this->simpanHarga->Jalankan($produk, $perSatuan, SumberPerubahanHarga::from($sumber));
        } catch (PelanggaranAturanBisnis $galat) {
            throw self::PetakanBidang($galat, array_keys($perSatuan), $bidangPerSatuan);
        }
    }

    public function HapusHargaSatuan(ProdukSatuan $satuan, string $sumber): void
    {
        $this->hapusHarga->Jalankan($satuan, SumberPerubahanHarga::from($sumber));
    }

    /**
     * @param  list<int>  $urutanId
     * @param  array<int, string>  $bidangPerSatuan
     */
    private static function PetakanBidang(PelanggaranAturanBisnis $galat, array $urutanId, array $bidangPerSatuan): PelanggaranAturanBisnis
    {
        if (preg_match('/^Satuan\.(\d+)(?:\.Harga)?(.*)$/', $galat->bidang, $cocok) !== 1) {
            return $galat;
        }

        $idProdukSatuan = $urutanId[(int) $cocok[1]] ?? null;
        $awalan = $idProdukSatuan === null ? null : ($bidangPerSatuan[$idProdukSatuan] ?? null);

        if ($awalan === null) {
            return $galat;
        }

        // Form produk (`…HargaAwal`) mempertahankan `.{j}.{JumlahMinimum|Harga}`; bidang tunggal (`HargaDasar`) tidak.
        $bidang = str_ends_with($awalan, '.HargaAwal') ? $awalan.$cocok[2] : $awalan;

        return new PelanggaranAturanBisnis($galat->kode, $galat->getMessage(), $bidang, $galat->statusHttp, $galat->detail);
    }
}
