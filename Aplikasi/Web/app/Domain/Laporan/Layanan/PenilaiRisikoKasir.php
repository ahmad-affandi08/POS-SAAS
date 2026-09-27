<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * F-14 anti-fraud (OWN-09, BR-09.3): skor risiko kasir 0–100 yang bisa dijelaskan (setiap poin punya alasan berteks),
 * dihitung dari pola dalam periode laporan. Pembanding = rata-rata semua kasir pada saring yang sama. Aturan:
 * - void tunai ≤ 10 menit setelah bayar: +15 per kejadian (maks 30);
 * - rasio void ≥ 2× rata-rata dan minimal 3 void: +20;
 * - rasio diskon (total diskon ÷ kotor) ≥ 2× rata-rata dan minimal 3 transaksi berdiskon: +15;
 * - rasio retur ≥ 2× rata-rata dan minimal 3 retur: +15;
 * - buka laci manual tanpa transaksi minimal 3 kali: +10;
 * - kas kurang saat tutup shift: +10, atau +20 bila total kekurangan melebihi toleransi selisih kas.
 * Tingkat: Tinggi ≥ 60, Sedang ≥ 30, selain itu Rendah. Skor adalah petunjuk untuk diperiksa, bukan bukti.
 */
final class PenilaiRisikoKasir
{
    public const MENIT_VOID_CEPAT = 10;

    /**
     * @param  array<int, array<string, mixed>>  $pola  per Id kasir: JumlahTransaksi, Kotor, JumlahVoid, VoidCepatTunai, JumlahRetur, JumlahBerdiskon, TotalDiskon, BukaLaciManual, ShiftSelisihKurang, SelisihKurang
     * @return array<int, array{Skor: int, Tingkat: string, Alasan: list<string>}>
     */
    public function Nilai(array $pola, Uang $toleransiSelisih): array
    {
        $rataVoid = self::Rata($pola, 'JumlahVoid', 'JumlahTransaksi');
        $rataRetur = self::Rata($pola, 'JumlahRetur', 'JumlahTransaksi');
        $rataDiskon = self::RataUang($pola, 'TotalDiskon', 'Kotor');
        $hasil = [];

        foreach ($pola as $idKasir => $p) {
            $skor = 0;
            $alasan = [];
            $transaksi = (int) ($p['JumlahTransaksi'] ?? 0);
            $void = (int) ($p['JumlahVoid'] ?? 0);
            $cepat = (int) ($p['VoidCepatTunai'] ?? 0);
            $retur = (int) ($p['JumlahRetur'] ?? 0);

            if ($cepat > 0) {
                $skor += min(30, 15 * $cepat);
                $alasan[] = "{$cepat} void tunai ≤ ".self::MENIT_VOID_CEPAT.' menit setelah bayar';
            }

            $rasioVoid = self::Rasio($void, $transaksi);

            if ($void >= 3 && $rataVoid->isPositive() && $rasioVoid->isGreaterThanOrEqualTo($rataVoid->multipliedBy(2))) {
                $skor += 20;
                $alasan[] = 'Void '.self::Persen($rasioVoid).' dari transaksi (rata-rata '.self::Persen($rataVoid).')';
            }

            $rasioDiskon = self::RasioUang((string) ($p['TotalDiskon'] ?? '0'), (string) ($p['Kotor'] ?? '0'));

            if ((int) ($p['JumlahBerdiskon'] ?? 0) >= 3 && $rataDiskon->isPositive() && $rasioDiskon->isGreaterThanOrEqualTo($rataDiskon->multipliedBy(2))) {
                $skor += 15;
                $alasan[] = 'Diskon '.self::Persen($rasioDiskon).' dari penjualan kotor (rata-rata '.self::Persen($rataDiskon).')';
            }

            $rasioRetur = self::Rasio($retur, $transaksi);

            if ($retur >= 3 && $rataRetur->isPositive() && $rasioRetur->isGreaterThanOrEqualTo($rataRetur->multipliedBy(2))) {
                $skor += 15;
                $alasan[] = "{$retur} retur, ".self::Persen($rasioRetur).' dari transaksi (rata-rata '.self::Persen($rataRetur).')';
            }

            $laci = (int) ($p['BukaLaciManual'] ?? 0);

            if ($laci >= 3) {
                $skor += 10;
                $alasan[] = "Buka laci tanpa transaksi {$laci} kali";
            }

            $kurang = Uang::Dari((string) ($p['SelisihKurang'] ?? '0'));

            if ((int) ($p['ShiftSelisihKurang'] ?? 0) > 0 && $kurang->Bandingkan(Uang::Nol()) > 0) {
                $melebihi = $kurang->Bandingkan($toleransiSelisih) > 0;
                $skor += $melebihi ? 20 : 10;
                $alasan[] = "Kas kurang {$kurang->FormatRupiah()} di {$p['ShiftSelisihKurang']} shift".($melebihi ? ' (melebihi toleransi)' : '');
            }

            $skor = min(100, $skor);
            $hasil[$idKasir] = ['Skor' => $skor, 'Tingkat' => $skor >= 60 ? 'Tinggi' : ($skor >= 30 ? 'Sedang' : 'Rendah'), 'Alasan' => $alasan];
        }

        return $hasil;
    }

    /** @param array<int, array<string, mixed>> $pola */
    private static function Rata(array $pola, string $pembilang, string $penyebut): BigDecimal
    {
        $atas = array_sum(array_map(fn (array $p): int => (int) ($p[$pembilang] ?? 0), $pola));
        $bawah = array_sum(array_map(fn (array $p): int => (int) ($p[$penyebut] ?? 0), $pola));

        return self::Rasio($atas, $bawah);
    }

    /** @param array<int, array<string, mixed>> $pola */
    private static function RataUang(array $pola, string $pembilang, string $penyebut): BigDecimal
    {
        $atas = BigDecimal::zero();
        $bawah = BigDecimal::zero();

        foreach ($pola as $p) {
            $atas = $atas->plus(BigDecimal::of((string) ($p[$pembilang] ?? '0')));
            $bawah = $bawah->plus(BigDecimal::of((string) ($p[$penyebut] ?? '0')));
        }

        return $bawah->isPositive() ? $atas->dividedBy($bawah, 6, RoundingMode::HalfUp) : BigDecimal::zero();
    }

    private static function Rasio(int $atas, int $bawah): BigDecimal
    {
        return $bawah > 0 ? BigDecimal::of($atas)->dividedBy($bawah, 6, RoundingMode::HalfUp) : BigDecimal::zero();
    }

    private static function RasioUang(string $atas, string $bawah): BigDecimal
    {
        $b = BigDecimal::of($bawah);

        return $b->isPositive() ? BigDecimal::of($atas)->dividedBy($b, 6, RoundingMode::HalfUp) : BigDecimal::zero();
    }

    private static function Persen(BigDecimal $rasio): string
    {
        return str_replace('.', ',', (string) $rasio->multipliedBy(100)->toScale(1, RoundingMode::HalfUp)).'%';
    }
}
