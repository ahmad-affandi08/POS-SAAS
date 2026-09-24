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
use App\Domain\Pajak\Model\KelompokPajak;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Str;
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

    public static function BuatKelompokPajak(string $nama = 'Barang kena PPN'): KelompokPajak
    {
        return KelompokPajak::query()->create(['Nama' => $nama]);
    }

    /**
     * Tenant siap mengisi produk lewat form: satuan pcs (standar), satuan kg (desimal), dan satu kelompok pajak.
     * Konteks tenant diatur ke tenant ini.
     *
     * @return array{Tenant: Tenant, Pemilik: Pengguna, Outlet: Outlet, Pcs: Satuan, Kg: Satuan, KelompokPajak: KelompokPajak}
     */
    public static function SiapkanTenantProduk(string $namaUsaha = 'Toko Sumber Rejeki', ?string $kodePaket = null): array
    {
        $hasil = self::BuatTenant($namaUsaha, $kodePaket);
        BantuanOrganisasi::AturKonteks($hasil['Tenant']->Id);

        return $hasil + [
            'Pcs' => self::BuatSatuan('Pieces', 'pcs', false, 'PCS'),
            'Kg' => self::BuatSatuan('Kilogram', 'kg', true, 'KG'),
            'KelompokPajak' => self::BuatKelompokPajak(),
        ];
    }

    /**
     * Isi form produk (tipe FE `FormProduk`) dengan satu satuan dasar tanpa barcode & harga.
     *
     * @param  array<string, mixed>  $timpa
     * @return array<string, mixed>
     */
    public static function IsiFormProduk(Satuan $satuanDasar, ?KelompokPajak $kelompokPajak, array $timpa = []): array
    {
        self::$urutan++;

        return array_replace([
            'Uuid' => (string) Str::ulid(),
            'Nama' => 'Kopi Bubuk Robusta Temanggung 250 gram #'.self::$urutan,
            'NamaStruk' => '',
            'Sku' => '',
            'Jenis' => JenisProduk::Stok->value,
            'UuidKategori' => null,
            'Merek' => '',
            'UuidSatuanDasar' => $satuanDasar->Uuid,
            'Pelacakan' => 'Tidak',
            'UuidKelompokPajak' => $kelompokPajak?->Uuid,
            'HargaTermasukPajak' => 'Ikut',
            'BolehMinus' => 'Ikut',
            'TampilDiPos' => true,
            'TampilOnline' => false,
            'Satuan' => [self::IsiSatuanForm($satuanDasar)],
            'AtributVarian' => [],
        ], $timpa);
    }

    /**
     * @param  list<string>  $barcode
     * @param  list<array{JumlahMinimum: string, Harga: string}>  $hargaAwal
     * @return array<string, mixed>
     */
    public static function IsiSatuanForm(Satuan $satuan, string $konversi = '1', array $barcode = [], array $hargaAwal = [], ?string $uuid = null, bool $defaultJual = false, bool $defaultBeli = false): array
    {
        return [
            'Uuid' => $uuid,
            'UuidSatuan' => $satuan->Uuid,
            'KonversiKeDasar' => $konversi,
            'DefaultJual' => $defaultJual,
            'DefaultBeli' => $defaultBeli,
            'Barcode' => $barcode,
            'HargaAwal' => $hargaAwal,
        ];
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
