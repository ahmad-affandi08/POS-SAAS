<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Jadwal penyusutan garis lurus (FIN-10), murni tanpa basis data.
 *
 * - Bulan perolehan ikut disusutkan penuh (pola fiskal: penyusutan dimulai pada bulan perolehan).
 * - Aset saldo awal: bulan antara periode perolehan dan `PeriodeMulai` dianggap sudah tercakup `AkumulasiAwal`;
 *   sisa nilai disusutkan rata selama sisa masa manfaat.
 * - Nilai per bulan dibulatkan ke bawah 2 desimal; bulan terakhir menyerap sisa pembulatan, sehingga total
 *   penyusutan tepat = harga perolehan − nilai sisa − akumulasi awal.
 */
final class PenghitungPenyusutan
{
    /**
     * @return list<array{Periode: string, Jumlah: Uang}>
     */
    public static function HitungJadwal(Uang $harga, Uang $nilaiSisa, Uang $akumulasiAwal, int $umurBulan, string $periodePerolehan, string $periodeMulai): array
    {
        $dasar = $harga->Kurangi($nilaiSisa)->Kurangi($akumulasiAwal);
        $mulai = self::KeTanggal($periodeMulai);
        $sudahLewat = max(0, self::SelisihBulan(self::KeTanggal($periodePerolehan), $mulai));
        $sisaBulan = $umurBulan - $sudahLewat;

        if ($sisaBulan <= 0 || $dasar->Bandingkan(Uang::Nol()) <= 0) {
            return [];
        }

        $perBulan = Uang::Dari(BigDecimal::of($dasar->KeString())->dividedBy($sisaBulan, 2, RoundingMode::Down));
        $jadwal = [];
        $total = Uang::Nol();

        for ($i = 0; $i < $sisaBulan; $i++) {
            $jumlah = $i === $sisaBulan - 1 ? $dasar->Kurangi($total) : $perBulan;
            $total = $total->Tambah($jumlah);
            $jadwal[] = ['Periode' => $mulai->addMonthsNoOverflow($i)->format('Y-m'), 'Jumlah' => $jumlah];
        }

        return $jadwal;
    }

    /** Jumlah bulan dari `$dari` ke `$ke` (keduanya tanggal 1). */
    public static function SelisihBulan(CarbonImmutable $dari, CarbonImmutable $ke): int
    {
        return ((int) $ke->format('Y') - (int) $dari->format('Y')) * 12 + (int) $ke->format('n') - (int) $dari->format('n');
    }

    public static function KeTanggal(string $periode): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $periode.'-01', 'UTC') ?: CarbonImmutable::parse($periode.'-01', 'UTC');
    }
}
