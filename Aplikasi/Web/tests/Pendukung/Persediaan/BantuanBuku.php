<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Layanan\Hpp\HasilHpp;
use App\Domain\Persediaan\Layanan\Hpp\KeadaanHpp;
use App\Domain\Persediaan\Layanan\Hpp\MasukanHpp;
use App\Domain\Persediaan\Layanan\Hpp\StrategiHpp;
use App\Domain\Persediaan\Model\SaldoStok;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Bantuan test buku stok & HPP F-05a Tim A (DesainF05a C.2/C.3): pembuat masukan strategi HPP (unit, tanpa
 * database) dan pembuat dokumen mutasi untuk `CatatMutasiStok` (fitur). Semua angka berupa string desimal.
 */
final class BantuanBuku
{
    private static int $idReferensi = 900000;

    /** Masuk bernilai ditentukan: `jumlah` > 0 dengan nilai V (dan HPP baris opsional). */
    public static function BuatMasukanDitentukan(string $jumlah, string $nilai, ?string $hppSatuan = null, ?int $idMutasiAsal = null, ?int $idBatchStok = null): MasukanHpp
    {
        return new MasukanHpp(
            Kuantitas::Dari($jumlah),
            ModeNilaiMutasi::Ditentukan,
            Uang::Dari($nilai),
            $hppSatuan === null ? null : BigDecimal::of($hppSatuan),
            $idBatchStok,
            $idMutasiAsal,
            'U/'.$jumlah.'/'.$nilai,
        );
    }

    public static function BuatMasukanBerjalan(string $jumlah, ?int $idBatchStok = null): MasukanHpp
    {
        return new MasukanHpp(Kuantitas::Dari($jumlah), ModeNilaiMutasi::Berjalan, idBatchStok: $idBatchStok, kunciBaris: 'U/'.$jumlah);
    }

    /**
     * Menerapkan satu langkah `[jumlah]` (berjalan) atau `[jumlah, nilai, hppSatuan?]` (ditentukan).
     *
     * @param  array{0: string, 1?: string, 2?: string|null}  $langkah
     */
    public static function TerapkanLangkah(StrategiHpp $strategi, KeadaanHpp $keadaan, array $langkah): HasilHpp
    {
        $masukan = isset($langkah[1])
            ? self::BuatMasukanDitentukan($langkah[0], $langkah[1], $langkah[2] ?? null)
            : self::BuatMasukanBerjalan($langkah[0]);

        return $strategi->Terapkan($keadaan, $masukan);
    }

    /** Σ JumlahSisa lapisan terbuka keadaan. */
    public static function HitungSisaLapisan(KeadaanHpp $keadaan): Kuantitas
    {
        $total = Kuantitas::Nol();

        foreach ($keadaan->AmbilLapisanTerbuka() as $lapisan) {
            $total = $total->Tambah($lapisan->jumlahSisa);
        }

        return $total;
    }

    /** Σ NilaiSisa lapisan terbuka keadaan. */
    public static function HitungNilaiLapisan(KeadaanHpp $keadaan): Uang
    {
        $total = Uang::Nol();

        foreach ($keadaan->AmbilLapisanTerbuka() as $lapisan) {
            $total = $total->Tambah($lapisan->nilaiSisa);
        }

        return $total;
    }

    /**
     * Menjalankan `aksi` dan mengembalikan PelanggaranAturanBisnis yang dilemparnya (gagal bila tidak ada).
     */
    public static function TangkapPelanggaran(callable $aksi): PelanggaranAturanBisnis
    {
        try {
            $aksi();
        } catch (PelanggaranAturanBisnis $galat) {
            return $galat;
        }

        throw new RuntimeException('Diharapkan PelanggaranAturanBisnis, tetapi aksi berhasil.');
    }

    /** Id referensi dokumen uji yang unik (dokumen sumber fiktif, misal nomor penerimaan). */
    public static function AmbilIdReferensiBaru(): int
    {
        return ++self::$idReferensi;
    }

    /**
     * Baris mutasi untuk `CatatMutasiStok`. `nilai` diisi = `Ditentukan`, kosong = `Berjalan`.
     */
    public static function BuatBaris(
        string $kunci,
        int $idProduk,
        int $idGudang,
        string $jumlah,
        ?string $nilai = null,
        JenisMutasi $jenis = JenisMutasi::StokAwal,
        ?string $hppSatuan = null,
        ?int $idMutasiAsal = null,
        ?DataBatchMasuk $batchMasuk = null,
        ?int $idBatchStok = null,
        ?string $nomorSeriMasuk = null,
        ?int $idNomorSeri = null,
    ): DataBarisMutasi {
        return new DataBarisMutasi(
            kunciBaris: $kunci,
            idProduk: $idProduk,
            idGudang: $idGudang,
            jenisMutasi: $jenis,
            jumlah: Kuantitas::Dari($jumlah),
            modeNilai: $nilai === null ? ModeNilaiMutasi::Berjalan : ModeNilaiMutasi::Ditentukan,
            nilai: $nilai === null ? null : Uang::Dari($nilai),
            hppSatuan: $hppSatuan === null ? null : BigDecimal::of($hppSatuan),
            batchMasuk: $batchMasuk,
            idBatchStok: $idBatchStok,
            nomorSeriMasuk: $nomorSeriMasuk,
            idNomorSeri: $idNomorSeri,
            idMutasiAsal: $idMutasiAsal,
        );
    }

    /**
     * Dokumen mutasi uji (tanggal bisnis bawaan 2026-09-24).
     *
     * @param  list<DataBarisMutasi>  $baris
     */
    public static function BuatDokumen(
        array $baris,
        JenisReferensiMutasi $jenis = JenisReferensiMutasi::PenyesuaianStok,
        ?int $idReferensi = null,
        string $tanggal = '2026-09-24',
        ?int $idPengguna = null,
        ?string $nomor = null,
    ): DataDokumenMutasi {
        $id = $idReferensi ?? self::AmbilIdReferensiBaru();

        return new DataDokumenMutasi(
            $jenis,
            $id,
            null,
            $nomor ?? 'PS/2026/09/'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            CarbonImmutable::parse($tanggal),
            $idPengguna,
            null,
            $baris,
        );
    }

    /**
     * @param  list<DataBarisMutasi>  $baris
     */
    public static function Catat(array $baris, JenisReferensiMutasi $jenis = JenisReferensiMutasi::PenyesuaianStok, ?int $idReferensi = null, string $tanggal = '2026-09-24'): HasilCatatMutasi
    {
        return app(CatatMutasiStok::class)->Jalankan(self::BuatDokumen($baris, $jenis, $idReferensi, $tanggal));
    }

    /** Masuk ditentukan satu baris (misal penerimaan/penyesuaian masuk) di dokumen baru. */
    public static function CatatMasuk(int $idProduk, int $idGudang, string $jumlah, string $nilai, JenisMutasi $jenis = JenisMutasi::PenyesuaianMasuk): HasilCatatMutasi
    {
        return self::Catat([self::BuatBaris('M/1', $idProduk, $idGudang, $jumlah, $nilai, $jenis)]);
    }

    /** Keluar berjalan satu baris (misal penjualan) di dokumen baru; `jumlah` positif = besaran keluar. */
    public static function CatatKeluar(int $idProduk, int $idGudang, string $jumlah, JenisMutasi $jenis = JenisMutasi::Penjualan): HasilCatatMutasi
    {
        return self::Catat([self::BuatBaris('K/1', $idProduk, $idGudang, '-'.$jumlah, null, $jenis)], JenisReferensiMutasi::Penjualan);
    }

    public static function AmbilSaldo(int $idProduk, int $idGudang): ?SaldoStok
    {
        return SaldoStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->first();
    }

    /**
     * Invarian buku stok tanpa jurnal (Tim A menguji mesin buku; invarian jurnal = Tim B/H).
     *
     * @return list<string>
     */
    public static function PeriksaInvarianBuku(int $idTenant, bool $fifo = false): array
    {
        return [
            ...PemeriksaInvarian::PeriksaSaldoStok($idTenant),
            ...PemeriksaInvarian::PeriksaRantaiMutasi($idTenant),
            ...PemeriksaInvarian::PeriksaNilaiNolSaatJumlahNol($idTenant),
            ...($fifo ? PemeriksaInvarian::PeriksaLapisanFifo($idTenant) : []),
            ...PemeriksaInvarian::PeriksaBatch($idTenant),
            ...PemeriksaInvarian::PeriksaNomorSeri($idTenant),
        ];
    }
}
