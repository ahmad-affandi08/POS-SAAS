<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Tenant\Model\Tenant;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;

/**
 * Prasyarat test pelacakan batch/nomor seri F-05a (DesainF05a C.4, Tim D): tenant + lokasi stok + produk tiap
 * pelacakan, baris stok awal untuk validator, dan baris `MutasiStok` mentah sebagai "riwayat stok" (tanpa mesin buku
 * stok Tim A). Panggil `BantuanPendaftaran::SiapkanPrasyarat()` dulu.
 */
final class BantuanPelacakan
{
    private static int $urutan = 0;

    /**
     * Tenant F-05a (konteks diatur ke tenant ini) beserta produk semua jenis/pelacakan dan lokasi stok kedua.
     *
     * @return array{Tenant: Tenant, Outlet: Outlet, Gudang: Gudang, GudangBelakang: Gudang, Pcs: Satuan, Produk: array{Stok: Produk, BahanBaku: Produk, Produksi: Produk, Konsinyasi: Produk, Jasa: Produk, Batch: Produk, Seri: Produk}}
     */
    public static function SiapkanTenant(string $namaUsaha = 'Apotek Sehat Sentosa', bool $stokBolehMinus = false): array
    {
        $t = BantuanPersediaan::SiapkanTenant($namaUsaha, stokBolehMinus: $stokBolehMinus);
        $produk = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $belakang = BantuanPersediaan::BuatGudang($t['Outlet']);
        BantuanOrganisasi::AturKonteks($t['Tenant']->Id);

        return [
            'Tenant' => $t['Tenant'],
            'Outlet' => $t['Outlet'],
            'Gudang' => $t['Gudang'],
            'GudangBelakang' => $belakang,
            'Pcs' => $t['Pcs'],
            'Produk' => $produk,
        ];
    }

    public static function Info(Produk $produk): DataInfoProdukStok
    {
        return app(InfoProdukStok::class)->AmbilBanyak([$produk->Id])[$produk->Id];
    }

    /**
     * @param  list<string>  $nomorSeri
     */
    public static function Baris(
        Produk $produk,
        string $jumlah,
        ?string $nomorBatch = null,
        ?string $tanggalKedaluwarsa = null,
        array $nomorSeri = [],
        string $hppSatuan = '18500.000000',
    ): DataBarisStokAwal {
        return new DataBarisStokAwal(
            $produk->Id,
            Kuantitas::Dari($jumlah),
            BigDecimal::of($hppSatuan),
            $nomorBatch,
            $tanggalKedaluwarsa === null ? null : CarbonImmutable::parse($tanggalKedaluwarsa),
            $nomorSeri,
        );
    }

    /**
     * Satu baris `MutasiStok` mentah (masuk, StokAwal) sebagai riwayat stok produk. Hanya untuk prasyarat test.
     *
     * @param  array<string, mixed>  $timpa
     */
    public static function BuatMutasiMentah(Produk $produk, Gudang $gudang, string $jumlah = '10.0000', array $timpa = []): MutasiStok
    {
        self::$urutan++;

        return MutasiStok::query()->create(array_replace([
            'IdProduk' => $produk->Id,
            'IdGudang' => $gudang->Id,
            'JenisMutasi' => JenisMutasi::StokAwal,
            'Jumlah' => $jumlah,
            'HppSatuan' => '18500.000000',
            'TotalHpp' => '0.00',
            'SelisihHpp' => '0.00',
            'SaldoSetelah' => $jumlah,
            'NilaiSetelah' => '0.00',
            'HppRataRataSetelah' => '18500.000000',
            'JenisReferensi' => JenisReferensiMutasi::StokAwal,
            'IdReferensi' => 900000 + self::$urutan,
            'KunciBaris' => 'P/'.self::$urutan,
            'TanggalBisnis' => '2026-09-24',
        ], $timpa));
    }
}
