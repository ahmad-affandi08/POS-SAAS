<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Aksi\PostingStokAwal;
use App\Domain\Persediaan\Aksi\SimpanStokAwal;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Data\DataStokAwal;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Model\StokAwal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Prasyarat test stok awal F-05a Tim C (DesainF05a F): baris masukan, draf lewat `SimpanStokAwal`, posting, isi
 * form HTTP, dan penjualan tiruan lewat buku stok (`CatatMutasiStok`, referensi Penjualan) untuk menguji
 * pembatalan yang terhalang stok terpakai dan stok awal setelah stok minus.
 */
final class BantuanStokAwal
{
    private static int $urutanPenjualan = 900000;

    /**
     * @param  list<string>  $nomorSeri
     */
    public static function Baris(Produk $produk, string $jumlah, string $hppSatuan, ?string $nomorBatch = null, ?string $kedaluwarsa = null, array $nomorSeri = []): DataBarisStokAwal
    {
        return new DataBarisStokAwal(
            $produk->Id,
            Kuantitas::Dari($jumlah),
            BigDecimal::of($hppSatuan),
            $nomorBatch,
            $kedaluwarsa === null ? null : CarbonImmutable::parse($kedaluwarsa),
            $nomorSeri,
        );
    }

    /**
     * @param  list<DataBarisStokAwal>  $baris
     */
    public static function Data(Gudang $gudang, array $baris, ?string $tanggal = null, ?string $uuid = null, ?string $catatan = 'Saldo awal hasil hitung fisik akhir bulan', ?string $versi = null): DataStokAwal
    {
        return new DataStokAwal(
            $uuid,
            $gudang->Id,
            CarbonImmutable::parse($tanggal ?? CarbonImmutable::now('Asia/Jakarta')->subDay()->format('Y-m-d')),
            $catatan,
            $baris,
            versiDiubahPada: $versi,
        );
    }

    /**
     * @param  list<DataBarisStokAwal>  $baris
     */
    public static function BuatDraf(Gudang $gudang, array $baris, ?string $tanggal = null, ?string $uuid = null): StokAwal
    {
        return app(SimpanStokAwal::class)->Jalankan(self::Data($gudang, $baris, $tanggal, $uuid ?? (string) Str::ulid()), null);
    }

    /**
     * @param  list<DataBarisStokAwal>  $baris
     */
    public static function BuatDanPosting(Gudang $gudang, array $baris, int $idPengguna, ?string $tanggal = null): StokAwal
    {
        return app(PostingStokAwal::class)->Jalankan(self::BuatDraf($gudang, $baris, $tanggal), $idPengguna);
    }

    /**
     * Penjualan tiruan: stok keluar `jumlah` (positif) dinilai berjalan oleh buku stok.
     */
    public static function Jual(Produk $produk, Gudang $gudang, string $jumlah, ?string $tanggal = null, ?int $idBatchStok = null, ?int $idNomorSeri = null): HasilCatatMutasi
    {
        self::$urutanPenjualan++;

        return app(CatatMutasiStok::class)->Jalankan(new DataDokumenMutasi(
            JenisReferensiMutasi::Penjualan,
            self::$urutanPenjualan,
            null,
            'PJ-UJI-'.self::$urutanPenjualan,
            CarbonImmutable::parse($tanggal ?? CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d')),
            null,
            null,
            [new DataBarisMutasi(
                kunciBaris: 'J/1',
                idProduk: $produk->Id,
                idGudang: $gudang->Id,
                jenisMutasi: JenisMutasi::Penjualan,
                jumlah: Kuantitas::Dari($jumlah)->Negasi(),
                modeNilai: ModeNilaiMutasi::Berjalan,
                idBatchStok: $idBatchStok,
                idNomorSeri: $idNomorSeri,
            )],
        ));
    }

    /**
     * Isi form HTTP (tipe FE `MasukanStokAwal`).
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    public static function IsiForm(Gudang $gudang, array $baris, ?string $tanggal = null, ?string $uuid = null): array
    {
        return [
            'Uuid' => $uuid ?? (string) Str::ulid(),
            'UuidGudang' => $gudang->Uuid,
            'Tanggal' => $tanggal ?? CarbonImmutable::now('Asia/Jakarta')->subDay()->format('Y-m-d'),
            'Catatan' => 'Stok awal toko setelah hitung fisik',
            'Baris' => $baris,
        ];
    }

    /**
     * @param  list<string>  $nomorSeri
     * @return array<string, mixed>
     */
    public static function IsiBaris(Produk $produk, string $jumlah, string $hppSatuan, ?string $nomorBatch = null, ?string $kedaluwarsa = null, array $nomorSeri = []): array
    {
        return [
            'UuidProduk' => $produk->Uuid,
            'Jumlah' => $jumlah,
            'HppSatuan' => $hppSatuan,
            'NomorBatch' => $nomorBatch,
            'TanggalKedaluwarsa' => $kedaluwarsa,
            'NomorSeri' => $nomorSeri,
        ];
    }
}
