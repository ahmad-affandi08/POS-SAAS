<?php

declare(strict_types=1);

namespace Tests\Pendukung\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Katalog\Pilihan\Aksi\SimpanKelompokPilihan;
use App\Domain\Katalog\Pilihan\Data\DataKelompokPilihan;
use App\Domain\Katalog\Pilihan\Data\DataPilihan;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Resep\Aksi\SimpanResep;
use App\Domain\Katalog\Resep\Data\DataBahanResep;
use App\Domain\Katalog\Resep\Data\DataResep;
use App\Domain\Katalog\Resep\Model\Resep;

/**
 * Prasyarat komposisi F-03 Tim 3 (kelompok pilihan, resep, paket) untuk test: satuan gram/mililiter, bahan baku,
 * produk resep/paket, kelompok pilihan dan resep lewat Aksi. Semua data dibuat di tenant konteks aktif; panggil
 * `BantuanKatalog::BuatTenant()` dulu.
 */
final class BantuanKomposisi
{
    public static function Satuan(string $nama, string $simbol, bool $bolehDesimal = true): Satuan
    {
        return Satuan::query()->where('Simbol', $simbol)->first() ?? BantuanKatalog::BuatSatuan($nama, $simbol, $bolehDesimal);
    }

    /** Bahan baku (tidak tampil di POS) bersatuan dasar `simbol`, tanpa harga jual. */
    public static function BuatBahan(string $nama = 'Biji Kopi Arabika Gayo Wine Process', string $simbol = 'g', string $namaSatuan = 'Gram'): Produk
    {
        return BantuanKatalog::BuatProduk(
            ['Nama' => $nama, 'Jenis' => JenisProduk::BahanBaku, 'TampilDiPos' => false],
            harga: null,
            satuan: self::Satuan($namaSatuan, $simbol),
        );
    }

    /** Menu jenis Resep (misal es kopi susu) bersatuan dasar cup. */
    public static function BuatProdukResep(string $nama = 'Es Kopi Susu Gula Aren Ukuran Besar', JenisProduk $jenis = JenisProduk::Resep): Produk
    {
        return BantuanKatalog::BuatProduk(['Nama' => $nama, 'Jenis' => $jenis], '25000.00', self::Satuan('Cup', 'cup', false));
    }

    public static function BuatPaket(string $nama = 'Paket Hemat Nasi Ayam Geprek + Es Teh Manis'): Produk
    {
        return BantuanKatalog::BuatProduk(['Nama' => $nama, 'Jenis' => JenisProduk::Paket], '35000.00');
    }

    /** Menambah satuan lain ke produk, misal kg = 1000 g. */
    public static function TambahSatuanProduk(Produk $produk, Satuan $satuan, string $konversi): ProdukSatuan
    {
        return ProdukSatuan::query()->create([
            'IdProduk' => $produk->Id,
            'IdSatuan' => $satuan->Id,
            'KonversiKeDasar' => $konversi,
            'DefaultJual' => false,
            'DefaultBeli' => false,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: Produk|null, 3?: string|null}>  $pilihan  [Nama, Harga, Bahan, Jumlah]
     */
    public static function BuatKelompokPilihan(string $nama = 'Level Gula', array $pilihan = [['Normal', '0'], ['Kurang Manis', '0'], ['Extra Manis', '2000']], int $minimal = 1, int $maksimal = 1): KelompokPilihan
    {
        return app(SimpanKelompokPilihan::class)->Jalankan(null, self::DataKelompokPilihan($nama, $pilihan, $minimal, $maksimal));
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: Produk|null, 3?: string|null}>  $pilihan
     */
    public static function DataKelompokPilihan(string $nama, array $pilihan, int $minimal = 1, int $maksimal = 1, bool $bolehUbahHarga = true): DataKelompokPilihan
    {
        return new DataKelompokPilihan($nama, $minimal, $maksimal, 0, array_map(fn (array $baris): DataPilihan => new DataPilihan(
            null,
            $baris[0],
            Uang::Dari($baris[1]),
            true,
            ($baris[2] ?? null)?->Id,
            isset($baris[3]) ? Kuantitas::Dari($baris[3]) : null,
        ), $pilihan), $bolehUbahHarga);
    }

    /**
     * Resep lewat Aksi. Bahan: [Produk, Jumlah, Satuan|null (null = satuan dasar bahan), PersenSusut].
     *
     * @param  list<array{0: Produk, 1: string, 2?: Satuan|null, 3?: string}>  $bahan
     */
    public static function SimpanResep(Produk $produk, array $bahan, string $jumlahHasil = '1', ?string $catatan = null, ?int $idPembuat = null): Resep
    {
        return app(SimpanResep::class)->Jalankan($produk, self::DataResep($bahan, $jumlahHasil, $catatan), $idPembuat);
    }

    /**
     * @param  list<array{0: Produk, 1: string, 2?: Satuan|null, 3?: string}>  $bahan
     */
    public static function DataResep(array $bahan, string $jumlahHasil = '1', ?string $catatan = null): DataResep
    {
        return new DataResep(Kuantitas::Dari($jumlahHasil), $catatan, array_map(fn (array $baris): DataBahanResep => new DataBahanResep(
            $baris[0]->Id,
            Kuantitas::Dari($baris[1]),
            isset($baris[2]) ? $baris[2]->Id : $baris[0]->IdSatuanDasar,
            $baris[3] ?? '0',
        ), $bahan));
    }

    /**
     * Isian HTTP resep (tipe FE, string desimal).
     *
     * @param  list<array{0: Produk, 1: string, 2?: Satuan|null, 3?: string}>  $bahan
     * @return array<string, mixed>
     */
    public static function IsianResep(array $bahan, string $jumlahHasil = '1', ?string $catatan = null): array
    {
        return [
            'JumlahHasil' => $jumlahHasil,
            'Catatan' => $catatan,
            'Bahan' => array_map(function (array $baris): array {
                $satuan = $baris[2] ?? Satuan::query()->findOrFail($baris[0]->IdSatuanDasar);

                return [
                    'UuidProdukBahan' => $baris[0]->Uuid,
                    'Jumlah' => $baris[1],
                    'UuidSatuan' => $satuan->Uuid,
                    'PersenSusut' => $baris[3] ?? '0',
                ];
            }, $bahan),
        ];
    }
}
