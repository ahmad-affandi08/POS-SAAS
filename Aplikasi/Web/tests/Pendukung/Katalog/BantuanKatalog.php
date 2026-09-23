<?php

declare(strict_types=1);

namespace Tests\Pendukung\Katalog;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Model\Tenant;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\TestCase;

/**
 * Prasyarat katalog F-03 untuk test semua tim: tenant, satuan, kategori, dan produk langsung lewat model (tanpa
 * Aksi, sehingga tanpa audit, RiwayatHarga, atau pemeriksaan batas paket), serta masuk sebagai anggota berperan
 * tertentu. Semua data dibuat di tenant konteks aktif. Panggil `BantuanPendaftaran::SiapkanPrasyarat()` dulu.
 */
final class BantuanKatalog
{
    private static int $urutan = 0;

    /**
     * Tenant baru (F-00) beserta Outlet Utama; konteks tenant diatur ke tenant ini.
     *
     * @return array{Tenant: Tenant, Pemilik: Pengguna, Outlet: Outlet}
     */
    public static function BuatTenant(string $namaUsaha = 'Toko Sumber Rejeki', ?string $kodePaket = null): array
    {
        return BantuanPanduanAwal::BuatTenant($namaUsaha, $kodePaket);
    }

    public static function BuatSatuan(string $nama = 'Pieces', string $simbol = 'pcs', bool $bolehDesimal = false, ?string $kodeStandar = null): Satuan
    {
        return Satuan::query()->create([
            'KodeStandar' => $kodeStandar,
            'Nama' => $nama,
            'Simbol' => $simbol,
            'BolehDesimal' => $bolehDesimal,
        ]);
    }

    public static function BuatKategori(string $nama = 'Minuman', ?Kategori $induk = null, int $urutan = 0): Kategori
    {
        return Kategori::query()->create(['Nama' => $nama, 'IdInduk' => $induk?->Id, 'Urutan' => $urutan]);
    }

    /**
     * Produk + `ProdukSatuan` dasar (jual & beli) + harga dasar (`JumlahMinimum` 1) bila `harga` tidak null.
     * Satuan bawaan = satuan `pcs` tenant (dibuat bila belum ada). SKU bawaan `UJI-000001`, dst.
     *
     * @param  array<string, mixed>  $atribut  kolom `Produk` yang menimpa nilai bawaan
     */
    public static function BuatProduk(array $atribut = [], ?string $harga = '18000.00', ?Satuan $satuan = null): Produk
    {
        self::$urutan++;
        $satuan ??= Satuan::query()->where('Simbol', 'pcs')->orderBy('Id')->first() ?? self::BuatSatuan();

        $produk = Produk::query()->create(array_replace([
            'Sku' => 'UJI-'.str_pad((string) self::$urutan, 6, '0', STR_PAD_LEFT),
            'Nama' => 'Sabun Mandi Cair Aroma Sereh Wangi 450 ml #'.self::$urutan,
            'Jenis' => JenisProduk::Stok,
            'IdSatuanDasar' => $satuan->Id,
            'Aktif' => true,
            'TampilDiPos' => true,
        ], $atribut));

        $produkSatuan = ProdukSatuan::query()->create([
            'IdProduk' => $produk->Id,
            'IdSatuan' => $produk->IdSatuanDasar,
            'KonversiKeDasar' => '1',
            'DefaultJual' => true,
            'DefaultBeli' => true,
        ]);

        if ($harga !== null) {
            ProdukHarga::query()->create([
                'IdProduk' => $produk->Id,
                'IdProdukSatuan' => $produkSatuan->Id,
                'IdDaftarHarga' => null,
                'JumlahMinimum' => '1',
                'Harga' => $harga,
            ]);
        }

        return $produk;
    }

    /**
     * Anggota baru dengan peran bawaan tertentu, lalu masuk ke back-office sebagai anggota itu.
     */
    public static function MasukSebagai(TestCase $tes, int $idTenant, PeranTenantBawaan $peran = PeranTenantBawaan::Pemilik, bool $semuaOutlet = true): TestCase
    {
        $pengguna = BantuanOrganisasi::TambahAnggota($idTenant, $peran, $semuaOutlet);
        BantuanOrganisasi::AturKonteks($idTenant);

        return BantuanOrganisasi::Masuk($tes, $pengguna, $idTenant);
    }
}
