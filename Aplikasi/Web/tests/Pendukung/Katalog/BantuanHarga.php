<?php

declare(strict_types=1);

namespace Tests\Pendukung\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;

/**
 * Prasyarat harga & pajak F-03 (Tim 2) untuk test: baris harga, satuan tambahan, daftar harga, dan jenis pajak
 * platform. Data dibuat langsung lewat model di tenant konteks aktif (tanpa Aksi).
 */
final class BantuanHarga
{
    public static function Baris(string $jumlahMinimum, string $harga): DataBarisHarga
    {
        return new DataBarisHarga(Kuantitas::Dari($jumlahMinimum), Uang::Dari($harga));
    }

    public static function SatuanDasar(Produk $produk): ProdukSatuan
    {
        return ProdukSatuan::query()->where('IdProduk', $produk->Id)->where('IdSatuan', $produk->IdSatuanDasar)->sole();
    }

    public static function TambahSatuan(Produk $produk, Satuan $satuan, string $konversi): ProdukSatuan
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
     * @param  array<string, mixed>  $atribut
     */
    public static function BuatDaftarHarga(string $nama = 'Harga Grosir', array $atribut = []): DaftarHarga
    {
        return DaftarHarga::query()->create(['Nama' => $nama] + $atribut);
    }

    public static function TambahHargaDaftar(DaftarHarga $daftar, ProdukSatuan $satuan, string $jumlahMinimum, string $harga): ProdukHarga
    {
        return ProdukHarga::query()->create([
            'IdProduk' => $satuan->IdProduk,
            'IdProdukSatuan' => $satuan->Id,
            'IdDaftarHarga' => $daftar->Id,
            'JumlahMinimum' => $jumlahMinimum,
            'Harga' => $harga,
        ]);
    }

    /**
     * Harga dasar satuan sebagai peta `JumlahMinimum => Harga`, urut jumlah.
     *
     * @return array<string, string>
     */
    public static function HargaDasar(ProdukSatuan $satuan): array
    {
        return ProdukHarga::query()->where('IdProdukSatuan', $satuan->Id)->whereNull('IdDaftarHarga')->orderBy('JumlahMinimum')
            ->pluck('Harga', 'JumlahMinimum')->all();
    }

    /** Jenis pajak platform (P-02): PPN, PBJT makanan & minuman, dan satu pajak daerah lain. */
    public static function SiapkanJenisPajak(): void
    {
        JenisPajak::query()->firstOrCreate(['Kode' => 'Ppn'], ['Nama' => 'PPN', 'Cakupan' => CakupanPajak::Nasional]);
        JenisPajak::query()->firstOrCreate(['Kode' => 'PbjtMakananMinuman'], ['Nama' => 'PBJT makanan & minuman (PB1)', 'Cakupan' => CakupanPajak::Daerah]);
        JenisPajak::query()->firstOrCreate(['Kode' => 'PbjtJasaHiburan'], ['Nama' => 'PBJT jasa kesenian & hiburan', 'Cakupan' => CakupanPajak::Daerah]);
    }

    /** Outlet tambahan di tenant konteks aktif. */
    public static function BuatOutlet(string $kode, string $nama): Outlet
    {
        return Outlet::query()->create(['IdMerek' => Merek::query()->value('Id'), 'Kode' => $kode, 'Nama' => $nama]);
    }

    /** Anggota berperan tertentu yang hanya ditugaskan ke outlet-outlet ini. */
    public static function TambahAnggotaOutlet(int $idTenant, PeranTenantBawaan $peran, Outlet ...$outlet): Pengguna
    {
        $pengguna = BantuanOrganisasi::TambahAnggota($idTenant, $peran, semuaOutlet: false);

        foreach ($outlet as $item) {
            OutletPengguna::query()->create(['IdOutlet' => $item->Id, 'IdPengguna' => $pengguna->Id, 'IdPeran' => BantuanOrganisasi::Peran($idTenant, $peran)->Id]);
        }

        return $pengguna;
    }
}
