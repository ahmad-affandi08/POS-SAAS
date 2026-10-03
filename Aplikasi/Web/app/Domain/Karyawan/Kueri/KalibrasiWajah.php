<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Karyawan\Model\Absensi;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * F-18 bagian 4 (D-37, K37): bahan kalibrasi ambang kemiripan wajah absensi web. Sebaran kemiripan absen yang
 * **diterima** (`Absensi.KemiripanWajahMasuk/Keluar`) dan percobaan yang **ditolak** karena wajah tidak cocok (log
 * audit `absensi.web.wajah-tidak-cocok`) dalam N hari terakhir, per kelompok 0,05. Angka dihitung dalam basis poin
 * (kemiripan × 10.000) supaya tanpa float.
 *
 * Percobaan ditolak tidak membawa outlet (log audit per karyawan), jadi pengguna terbatas outlet melihat penolakan
 * seluruh toko; absen diterima mengikuti batas outlet.
 */
final class KalibrasiWajah
{
    public const HARI_BAWAAN = 30;

    /** Batas bawah kelompok pertama yang dirinci; di bawahnya digabung jadi satu kelompok. */
    private const AWAL_BP = 3000;

    private const LEBAR_BP = 500;

    /** Di bawah jumlah ini angka belum layak dipakai menyetel ambang. */
    public const MINIMAL_DATA = 50;

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Ambang: string, Hari: int, JumlahDiterima: int, JumlahDitolak: int, PersenDitolak: string|null, TerendahDiterima: string|null, MedianDiterima: string|null, TertinggiDitolak: string|null, CukupData: bool, Kelompok: list<array{Dari: string, Sampai: string, Diterima: int, Ditolak: int}>}
     */
    public function Ambil(?array $idOutletBoleh, CarbonImmutable $sekarang, int $hari = self::HARI_BAWAAN): array
    {
        $sejak = $sekarang->subDays($hari);
        $diterima = [];

        Absensi::query()
            ->where('Sumber', Absensi::SUMBER_WEB)
            ->where('MasukPada', '>=', $sejak->utc())
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->get(['KemiripanWajahMasuk', 'KemiripanWajahKeluar'])
            ->each(function (Absensi $a) use (&$diterima): void {
                foreach ([$a->KemiripanWajahMasuk, $a->KemiripanWajahKeluar] as $nilai) {
                    if ($nilai !== null) {
                        $diterima[] = self::KeBasisPoin((string) $nilai);
                    }
                }
            });

        $ditolak = [];

        LogAudit::query()
            ->where('Peristiwa', 'absensi.web.wajah-tidak-cocok')
            ->where('DibuatPada', '>=', $sejak->utc())
            ->get(['NilaiBaru'])
            ->each(function (LogAudit $log) use (&$ditolak): void {
                $nilai = $log->NilaiBaru['Kemiripan'] ?? null;

                if (is_string($nilai) && is_numeric($nilai)) {
                    $ditolak[] = self::KeBasisPoin($nilai);
                }
            });

        sort($diterima);
        sort($ditolak);
        $total = count($diterima) + count($ditolak);

        return [
            'Ambang' => (string) BigDecimal::of((string) config('karyawan.AmbangKemiripanWajah'))->toScale(2, RoundingMode::HalfUp),
            'Hari' => $hari,
            'JumlahDiterima' => count($diterima),
            'JumlahDitolak' => count($ditolak),
            'PersenDitolak' => $total === 0 ? null : (string) BigDecimal::of(count($ditolak) * 100)->dividedBy($total, 1, RoundingMode::HalfUp),
            'TerendahDiterima' => $diterima === [] ? null : self::KeTeks($diterima[0]),
            'MedianDiterima' => $diterima === [] ? null : self::KeTeks($diterima[intdiv(count($diterima) - 1, 2)]),
            'TertinggiDitolak' => $ditolak === [] ? null : self::KeTeks($ditolak[count($ditolak) - 1]),
            'CukupData' => $total >= self::MINIMAL_DATA,
            'Kelompok' => self::SusunKelompok($diterima, $ditolak),
        ];
    }

    /**
     * @param  list<int>  $diterima
     * @param  list<int>  $ditolak
     * @return list<array{Dari: string, Sampai: string, Diterima: int, Ditolak: int}>
     */
    private static function SusunKelompok(array $diterima, array $ditolak): array
    {
        $batas = [0, ...range(self::AWAL_BP, 10000, self::LEBAR_BP)];
        $hitungDiterima = self::HitungPerKelompok($diterima, count($batas) - 1);
        $hitungDitolak = self::HitungPerKelompok($ditolak, count($batas) - 1);
        $kelompok = [];

        for ($i = 0; $i < count($batas) - 1; $i++) {
            $kelompok[] = [
                'Dari' => self::KeTeks($batas[$i]),
                'Sampai' => self::KeTeks($batas[$i + 1]),
                'Diterima' => $hitungDiterima[$i] ?? 0,
                'Ditolak' => $hitungDitolak[$i] ?? 0,
            ];
        }

        return $kelompok;
    }

    /**
     * Kelompok 0 = di bawah AWAL_BP; kelompok terakhir ikut memuat nilai 1,0000 tepat.
     *
     * @param  list<int>  $daftar
     * @return array<int, int>
     */
    private static function HitungPerKelompok(array $daftar, int $jumlahKelompok): array
    {
        $hitung = [];

        foreach ($daftar as $bp) {
            $indeks = $bp < self::AWAL_BP ? 0 : min($jumlahKelompok - 1, 1 + intdiv($bp - self::AWAL_BP, self::LEBAR_BP));
            $hitung[$indeks] = ($hitung[$indeks] ?? 0) + 1;
        }

        return $hitung;
    }

    private static function KeBasisPoin(string $nilai): int
    {
        return BigDecimal::of($nilai)->multipliedBy(10000)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    private static function KeTeks(int $bp): string
    {
        return (string) BigDecimal::ofUnscaledValue($bp, 4)->toScale(2, RoundingMode::Down);
    }
}
