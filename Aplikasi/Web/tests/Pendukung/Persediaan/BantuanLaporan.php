<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Enum\SumberStokAwal;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

/**
 * Prasyarat test Tim F (laporan saldo/kartu stok, penyedia, pengaturan; DesainF05a G). Tim F hanya MEMBACA tabel
 * persediaan, jadi baris `MutasiStok`/`SaldoStok`/`StokAwal` ditulis langsung di sini (tanpa mesin buku stok Tim A)
 * dengan rantai SaldoSetelah/NilaiSetelah dan cache SaldoStok yang konsisten, sehingga `PemeriksaInvarian` tetap
 * bisa dipakai. Konteks tenant harus sudah diatur.
 */
final class BantuanLaporan
{
    private static int $urutan = 0;

    /**
     * Satu baris mutasi (jumlah & nilai bertanda) di ujung rantai pasangan (produk, lokasi) + pembaruan SaldoStok.
     * HppRataRataSetelah = NilaiSetelah ÷ SaldoSetelah bila jumlah setelahnya positif, selain itu HPP sebelumnya.
     *
     * @param  array<string, mixed>  $timpa  kolom MutasiStok lain (misal NomorReferensi, UuidReferensi, DibuatOleh)
     */
    public static function CatatMutasi(
        Produk $produk,
        Gudang $gudang,
        string $jumlah,
        string $totalHpp,
        string $tanggalBisnis = '2026-09-01',
        JenisMutasi $jenis = JenisMutasi::StokAwal,
        JenisReferensiMutasi $referensi = JenisReferensiMutasi::StokAwal,
        array $timpa = [],
    ): MutasiStok {
        self::$urutan++;
        $saldo = SaldoStok::query()->firstOrNew(['IdProduk' => $produk->Id, 'IdGudang' => $gudang->Id]);
        $q = BigDecimal::of($saldo->JumlahTersedia)->plus($jumlah)->toScale(4);
        $n = BigDecimal::of($saldo->NilaiPersediaan)->plus($totalHpp)->toScale(2);
        $jumlahMutlak = BigDecimal::of($jumlah)->abs();
        $hppSatuan = $jumlahMutlak->isZero() ? BigDecimal::zero()->toScale(6) : BigDecimal::of($totalHpp)->abs()->dividedBy($jumlahMutlak, 6, RoundingMode::HalfUp);
        $hppSetelah = $q->isPositive() ? $n->dividedBy($q, 6, RoundingMode::HalfUp) : ($saldo->HppRataRata === null ? null : BigDecimal::of($saldo->HppRataRata));

        $mutasi = MutasiStok::query()->create(array_replace([
            'IdProduk' => $produk->Id,
            'IdGudang' => $gudang->Id,
            'JenisMutasi' => $jenis,
            'Jumlah' => $jumlah,
            'HppSatuan' => (string) $hppSatuan,
            'TotalHpp' => $totalHpp,
            'SelisihHpp' => '0.00',
            'SaldoSetelah' => (string) $q,
            'NilaiSetelah' => (string) $n,
            'HppRataRataSetelah' => $hppSetelah === null ? null : (string) $hppSetelah,
            'JenisReferensi' => $referensi,
            'IdReferensi' => 900000 + self::$urutan,
            'KunciBaris' => 'F/'.self::$urutan,
            'TanggalBisnis' => $tanggalBisnis,
        ], $timpa));

        $saldo->fill([
            'JumlahTersedia' => (string) $q,
            'NilaiPersediaan' => (string) $n,
            'HppRataRata' => $hppSetelah === null ? null : (string) $hppSetelah,
            'IdMutasiStokTerakhir' => $mutasi->Id,
        ])->save();

        return $mutasi;
    }

    /**
     * Dokumen stok awal mentah berstatus apa pun dengan baris `[Produk, jumlah, hppSatuan]` (Nilai dihitung).
     *
     * @param  list<array{0: Produk, 1: string, 2: string}>  $baris
     */
    public static function BuatStokAwal(Gudang $gudang, StatusStokAwal $status, array $baris, string $tanggal = '2026-09-01'): StokAwal
    {
        self::$urutan++;
        $total = BigDecimal::zero();

        foreach ($baris as [, $jumlah, $hpp]) {
            $total = $total->plus(BigDecimal::of($jumlah)->multipliedBy($hpp)->toScale(2, RoundingMode::HalfUp));
        }

        // Kepala dibuat sekali dengan TotalNilai final: dokumen non-Draf tidak boleh diubah lagi (penjaga model #8).
        $stokAwal = StokAwal::query()->create([
            'Uuid' => (string) Str::ulid(),
            'Nomor' => $status === StatusStokAwal::Diposting || $status === StatusStokAwal::Dibatalkan ? 'SA/2026/09/'.str_pad((string) self::$urutan, 4, '0', STR_PAD_LEFT) : null,
            'IdGudang' => $gudang->Id,
            'IdOutlet' => $gudang->IdOutlet,
            'Tanggal' => $tanggal,
            'Status' => $status,
            'Sumber' => SumberStokAwal::Manual,
            'JumlahBaris' => count($baris),
            'TotalNilai' => (string) $total,
        ]);

        foreach ($baris as $i => [$produk, $jumlah, $hpp]) {
            $nilai = BigDecimal::of($jumlah)->multipliedBy($hpp)->toScale(2, RoundingMode::HalfUp);
            StokAwalDetail::query()->create([
                'IdStokAwal' => $stokAwal->Id,
                'Urutan' => $i + 1,
                'IdProduk' => $produk->Id,
                'NamaProduk' => $produk->Nama,
                'Sku' => $produk->Sku,
                'Jumlah' => $jumlah,
                'HppSatuan' => $hpp,
                'Nilai' => (string) $nilai,
            ]);
        }

        return $stokAwal;
    }
}
