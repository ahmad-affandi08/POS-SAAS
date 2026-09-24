<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Akuntansi\Aksi\TambahkanAkunTemplate;
use App\Domain\Akuntansi\Data\DataAkunTemplate;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Tenant\Model\Tenant;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\TestCase;

/**
 * Prasyarat persediaan F-05a untuk test semua tim (DesainF05a G, pemilik Tim H setelah Tim 0): tenant ber-COA
 * template (semua peran akun persediaan terpetakan), lokasi stok, produk tiap jenis/pelacakan, metode HPP, dan
 * kunci periode. Data dibuat langsung (tanpa Aksi persediaan). Panggil `BantuanPendaftaran::SiapkanPrasyarat()`
 * dulu. Konteks tenant diatur ke tenant yang dibuat.
 */
final class BantuanPersediaan
{
    private static int $urutan = 0;

    /**
     * Tenant F-00 + satuan (pcs, kg) + COA template sektor `kodeTemplate` beserta pemetaan peran akun, metode HPP,
     * dan izin stok minus tenant. `Gudang` = lokasi stok toko Outlet Utama.
     *
     * @return array{Tenant: Tenant, Pemilik: Pengguna, Outlet: Outlet, Gudang: Gudang, Pcs: Satuan, Kg: Satuan}
     */
    public static function SiapkanTenant(
        string $namaUsaha = 'Toko Sembako Berkah Jaya',
        MetodeHpp $metodeHpp = MetodeHpp::RataRata,
        bool $stokBolehMinus = false,
        string $kodeTemplate = 'RTL-GEN',
    ): array {
        $hasil = BantuanKatalog::SiapkanTenantProduk($namaUsaha);
        self::TerapkanCoaTemplate($kodeTemplate);
        self::AturMetodeHpp($hasil['Tenant'], $metodeHpp, $stokBolehMinus);
        BantuanOrganisasi::AturKonteks($hasil['Tenant']->Id);

        return [
            'Tenant' => $hasil['Tenant'],
            'Pemilik' => $hasil['Pemilik'],
            'Outlet' => $hasil['Outlet'],
            'Gudang' => Gudang::query()->where('IdOutlet', $hasil['Outlet']->Id)->orderBy('Id')->firstOrFail(),
            'Pcs' => $hasil['Pcs'],
            'Kg' => $hasil['Kg'],
        ];
    }

    /** COA + pemetaan peran akun dari isi template sektor (tanpa langkah panduan awal lain) untuk tenant konteks. */
    public static function TerapkanCoaTemplate(string $kodeTemplate = 'RTL-GEN'): void
    {
        $isi = BantuanPanduanAwal::IsiTemplateAwal($kodeTemplate);
        $akun = [];

        foreach (is_array($isi['Akun'] ?? null) ? $isi['Akun'] : [] as $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $akun[] = new DataAkunTemplate(
                (string) ($baris['Kode'] ?? ''),
                (string) ($baris['Nama'] ?? ''),
                TipeAkun::from((string) ($baris['Tipe'] ?? '')),
                SaldoNormal::from((string) ($baris['SaldoNormal'] ?? '')),
            );
        }

        $pemetaan = [];

        foreach (is_array($isi['PemetaanAkun'] ?? null) ? $isi['PemetaanAkun'] : [] as $kunci => $kode) {
            $pemetaan[(string) $kunci] = (string) $kode;
        }

        app(TambahkanAkunTemplate::class)->Jalankan($akun, $pemetaan);
    }

    /** Menulis `Tenant.Pengaturan.MetodeHpp` & `StokBolehMinus` langsung (prasyarat test, bukan Aksi). */
    public static function AturMetodeHpp(Tenant|int $tenant, MetodeHpp $metode, ?bool $stokBolehMinus = null): void
    {
        $model = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);
        $model->refresh();
        $pengaturan = $model->Pengaturan ?? [];
        $pengaturan['MetodeHpp'] = $metode->value;

        if ($stokBolehMinus !== null) {
            $pengaturan['StokBolehMinus'] = $stokBolehMinus;
        }

        $model->Pengaturan = $pengaturan;
        $model->save();
    }

    public static function BuatGudang(Outlet $outlet, string $nama = 'Gudang Belakang', JenisGudang $jenis = JenisGudang::Gudang): Gudang
    {
        self::$urutan++;
        BantuanOrganisasi::AturKonteks($outlet->IdTenant);

        return Gudang::query()->create([
            'IdOutlet' => $outlet->Id,
            'Kode' => 'GDG-'.str_pad((string) self::$urutan, 3, '0', STR_PAD_LEFT),
            'Nama' => $nama,
            'Jenis' => $jenis,
        ]);
    }

    /**
     * Produk tiap jenis/pelacakan yang dipakai test F-05a, satuan dasar pcs (BahanBaku: kg desimal).
     *
     * @return array{Stok: Produk, BahanBaku: Produk, Produksi: Produk, Konsinyasi: Produk, Jasa: Produk, Batch: Produk, Seri: Produk}
     */
    public static function BuatProdukSemuaJenis(Satuan $pcs, Satuan $kg): array
    {
        return [
            'Stok' => BantuanKatalog::BuatProduk(['Nama' => 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter', 'Jenis' => JenisProduk::Stok], '38500.00', $pcs),
            'BahanBaku' => BantuanKatalog::BuatProduk(['Nama' => 'Gula Pasir Kristal Putih Curah', 'Jenis' => JenisProduk::BahanBaku, 'TampilDiPos' => false], null, $kg),
            'Produksi' => BantuanKatalog::BuatProduk(['Nama' => 'Roti Sobek Cokelat Keju Produksi Sendiri', 'Jenis' => JenisProduk::Produksi], '25000.00', $pcs),
            'Konsinyasi' => BantuanKatalog::BuatProduk(['Nama' => 'Keripik Singkong Pedas Titipan Bu Sari 200 gram', 'Jenis' => JenisProduk::Konsinyasi], '12000.00', $pcs),
            'Jasa' => BantuanKatalog::BuatProduk(['Nama' => 'Jasa Antar Belanja Dalam Kota', 'Jenis' => JenisProduk::Jasa], '10000.00', $pcs),
            'Batch' => BantuanKatalog::BuatProduk(['Nama' => 'Susu UHT Full Cream 1 Liter (batch & kedaluwarsa)', 'Jenis' => JenisProduk::Stok, 'Pelacakan' => PelacakanProduk::Batch], '19500.00', $pcs),
            'Seri' => BantuanKatalog::BuatProduk(['Nama' => 'Rice Cooker Digital 1,8 Liter (nomor seri)', 'Jenis' => JenisProduk::Stok, 'Pelacakan' => PelacakanProduk::Seri], '675000.00', $pcs),
        ];
    }

    /** Mengunci periode `YYYY-MM` tenant konteks (F-05a tidak punya UI penguncian; F-15). */
    public static function KunciPeriode(string $periode, ?int $olehIdPengguna = null): KunciPeriode
    {
        return KunciPeriode::query()->create(['Periode' => $periode, 'DikunciPada' => now(), 'DikunciOleh' => $olehIdPengguna]);
    }

    /** Masuk ke back-office sebagai anggota baru berperan `peran` di tenant ini. */
    public static function MasukSebagai(TestCase $tes, int $idTenant, PeranTenantBawaan $peran = PeranTenantBawaan::Pemilik, bool $semuaOutlet = true): TestCase
    {
        return BantuanKatalog::MasukSebagai($tes, $idTenant, $peran, $semuaOutlet);
    }
}
