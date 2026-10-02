<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * X6 insight mingguan ke pemilik (v3.79): ringkasan minggu lalu (Senin–Minggu tanggal bisnis) dibanding minggu
 * sebelumnya, dihitung dari kueri publik `AgregatPenjualan` (bersih = kotor − diskon − retur) dan saran restock
 * `LaporanStok` (termasuk faktor musim). Isi: total & persentase perubahan, rata-rata per transaksi, hari teramai,
 * lima produk terlaris, tiga produk naik & turun terbesar, produk yang habis ≤ 7 hari, dan pengingat Lebaran bila
 * periode restock sudah masuk musim Lebaran. Tanpa penjualan di kedua minggu = `null` (tidak dikirim).
 */
final class InsightMingguan
{
    public const JUMLAH_TERLARIS = 5;

    public const JUMLAH_PERUBAHAN = 3;

    public const HARI_RESTOCK = 7;

    public function __construct(
        private readonly AgregatPenjualan $agregat,
        private readonly LaporanStok $stok,
    ) {}

    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet
     * @return array{Dari: string, Sampai: string, Bersih: string, BersihSebelumnya: string, PersenPerubahan: string|null, JumlahTransaksi: int, JumlahTransaksiSebelumnya: int, RataTransaksi: string, HariTeramai: array{Tanggal: string, Bersih: string}|null, Terlaris: list<array{NamaProduk: string, Qty: string, Bersih: string}>, Naik: list<array{NamaProduk: string, Selisih: string}>, Turun: list<array{NamaProduk: string, Selisih: string}>, Restock: list<array{NamaProduk: string, NamaGudang: string, HariHabis: int, SaranBeli: string, SimbolSatuan: string}>, Lebaran: array{Tanggal: string, SisaHari: int}|null}|null
     */
    public function Susun(?array $idOutlet, CarbonImmutable $hariIni): ?array
    {
        $dari = $hariIni->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
        $sampai = $dari->addDays(6);
        $ini = new DataSaringLaporanPenjualan($dari, $sampai, $idOutlet);
        $lalu = new DataSaringLaporanPenjualan($dari->subWeek(), $sampai->subWeek(), $idOutlet);
        $total = $this->agregat->Total($ini);
        $totalLalu = $this->agregat->Total($lalu);

        if ($total->jumlahTransaksi === 0 && $totalLalu->jumlahTransaksi === 0) {
            return null;
        }

        $produk = $this->agregat->PerProduk($ini);
        $produkLalu = $this->agregat->PerProduk($lalu);

        return [
            'Dari' => $dari->toDateString(),
            'Sampai' => $sampai->toDateString(),
            'Bersih' => $total->Bersih()->KeString(),
            'BersihSebelumnya' => $totalLalu->Bersih()->KeString(),
            'PersenPerubahan' => self::HitungPersen($total->Bersih(), $totalLalu->Bersih()),
            'JumlahTransaksi' => $total->jumlahTransaksi,
            'JumlahTransaksiSebelumnya' => $totalLalu->jumlahTransaksi,
            'RataTransaksi' => $total->RataRataKeranjang()->KeString(),
            'HariTeramai' => $this->AmbilHariTeramai($ini),
            'Terlaris' => array_values(array_map(fn (array $p): array => [
                'NamaProduk' => $p['NamaProduk'],
                'Qty' => $p['Qty'],
                'Bersih' => $p['Bersih'],
            ], array_slice(array_values(array_filter($produk, fn (array $p): bool => BigDecimal::of($p['Bersih'])->isPositive())), 0, self::JUMLAH_TERLARIS))),
            ...self::BandingkanProduk($produk, $produkLalu),
            ...$this->AmbilStok($idOutlet, $hariIni),
        ];
    }

    /** Persen perubahan satu desimal (naik positif); minggu sebelumnya nol = `null`. */
    public static function HitungPersen(Uang $sekarang, Uang $sebelumnya): ?string
    {
        $dasar = BigDecimal::of($sebelumnya->KeString());

        if (! $dasar->isPositive()) {
            return null;
        }

        return (string) BigDecimal::of($sekarang->KeString())->minus($dasar)->multipliedBy(100)->dividedBy($dasar, 1, RoundingMode::HalfUp);
    }

    /**
     * @param  list<array{IdProduk: int, NamaProduk: string, Bersih: string}>  $ini
     * @param  list<array{IdProduk: int, NamaProduk: string, Bersih: string}>  $lalu
     * @return array{Naik: list<array{NamaProduk: string, Selisih: string}>, Turun: list<array{NamaProduk: string, Selisih: string}>}
     */
    public static function BandingkanProduk(array $ini, array $lalu): array
    {
        $selisih = [];

        foreach ([[$ini, 1], [$lalu, -1]] as [$daftar, $arah]) {
            foreach ($daftar as $p) {
                $lama = $selisih[$p['IdProduk']] ?? ['NamaProduk' => $p['NamaProduk'], 'Selisih' => BigDecimal::zero()];
                $nilai = BigDecimal::of($p['Bersih']);
                $lama['Selisih'] = $lama['Selisih']->plus($arah === 1 ? $nilai : $nilai->negated());
                $selisih[$p['IdProduk']] = $lama;
            }
        }

        $naik = array_values(array_filter($selisih, fn (array $s): bool => $s['Selisih']->isPositive()));
        $turun = array_values(array_filter($selisih, fn (array $s): bool => $s['Selisih']->isNegative()));
        usort($naik, fn (array $a, array $b): int => $b['Selisih']->compareTo($a['Selisih']) ?: strcmp($a['NamaProduk'], $b['NamaProduk']));
        usort($turun, fn (array $a, array $b): int => $a['Selisih']->compareTo($b['Selisih']) ?: strcmp($a['NamaProduk'], $b['NamaProduk']));
        $petakan = fn (array $s): array => ['NamaProduk' => $s['NamaProduk'], 'Selisih' => Uang::Dari($s['Selisih'])->KeString()];

        return [
            'Naik' => array_map($petakan, array_slice($naik, 0, self::JUMLAH_PERUBAHAN)),
            'Turun' => array_map($petakan, array_slice($turun, 0, self::JUMLAH_PERUBAHAN)),
        ];
    }

    /** @return array{Tanggal: string, Bersih: string}|null */
    private function AmbilHariTeramai(DataSaringLaporanPenjualan $saring): ?array
    {
        $teramai = null;

        foreach ($this->agregat->Agregasi($saring, ['Tanggal']) as $baris) {
            $bersih = $baris['Agregat']->Bersih();

            if ($bersih->Bandingkan(Uang::Nol()) > 0 && ($teramai === null || $bersih->Bandingkan($teramai['Uang']) > 0)) {
                $teramai = ['Tanggal' => $baris['Kunci'][0], 'Uang' => $bersih];
            }
        }

        return $teramai === null ? null : ['Tanggal' => $teramai['Tanggal'], 'Bersih' => $teramai['Uang']->KeString()];
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return array{Restock: list<array{NamaProduk: string, NamaGudang: string, HariHabis: int, SaranBeli: string, SimbolSatuan: string}>, Lebaran: array{Tanggal: string, SisaHari: int}|null}
     */
    private function AmbilStok(?array $idOutlet, CarbonImmutable $hariIni): array
    {
        $saran = $this->stok->SaranRestock($idOutlet, $hariIni, '', 14);
        $restock = [];

        foreach ($saran['Baris'] as $b) {
            if (is_int($b['HariHabis']) && $b['HariHabis'] <= self::HARI_RESTOCK && BigDecimal::of((string) $b['SaranBeli'])->isPositive()) {
                $restock[] = [
                    'NamaProduk' => (string) $b['NamaProduk'],
                    'NamaGudang' => (string) $b['NamaGudang'],
                    'HariHabis' => $b['HariHabis'],
                    'SaranBeli' => (string) $b['SaranBeli'],
                    'SimbolSatuan' => (string) $b['SimbolSatuan'],
                ];
            }
        }

        $lebaran = $saran['Musim']['Lebaran'];

        return [
            'Restock' => array_slice($restock, 0, self::JUMLAH_TERLARIS),
            'Lebaran' => $lebaran === null || $saran['Musim']['Jenis'] !== 'Lebaran'
                ? null
                : ['Tanggal' => $lebaran, 'SisaHari' => (int) $hariIni->startOfDay()->diffInDays(CarbonImmutable::parse($lebaran, $hariIni->getTimezone()), false)],
        ];
    }
}
