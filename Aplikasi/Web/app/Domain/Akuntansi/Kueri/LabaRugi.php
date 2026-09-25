<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Laba rugi (F-13a, FIN-07 P0, tipe FE `PropsLabaRugi`): pendapatan − HPP = laba kotor − beban = laba bersih, per
 * akun dalam kelompok tipe akun, dibandingkan dengan periode sebelumnya yang sama panjang (bulan penuh → bulan
 * penuh sebelumnya). Satu kueri agregat per akun untuk kedua periode (DECIMAL, eksak). Pendapatan = kredit − debit
 * (akun kontra seperti diskon/retur penjualan mengurangi), HPP & beban = debit − kredit.
 */
final class LabaRugi
{
    private const KELOMPOK = [
        'Pendapatan' => ['Tipe' => TipeAkun::Pendapatan, 'Label' => 'Pendapatan', 'LabelTotal' => 'Total pendapatan'],
        'Hpp' => ['Tipe' => TipeAkun::Hpp, 'Label' => 'Harga pokok penjualan', 'LabelTotal' => 'Total HPP'],
        'Beban' => ['Tipe' => TipeAkun::Beban, 'Label' => 'Beban', 'LabelTotal' => 'Total beban'],
    ];

    /**
     * @return array{Periode: array{Dari: string, Sampai: string, DariSebelumnya: string, SampaiSebelumnya: string}, Baris: list<array<string, mixed>>, Ringkasan: array<string, array{Nilai: string, NilaiSebelumnya: string}>}
     */
    public function Ambil(SaringLaporanKeuangan $saring): array
    {
        [$dariLalu, $sampaiLalu] = self::HitungPeriodeSebelumnya($saring->dari, $saring->sampai);
        $akun = Akun::query()
            ->whereIn('Jenis', [TipeAkun::Pendapatan->value, TipeAkun::Hpp->value, TipeAkun::Beban->value])
            ->orderBy('Kode')
            ->get();
        $agregat = $akun->isEmpty() ? collect() : $saring->TerapkanOutlet(JurnalDetail::query()
            ->whereIn('IdAkun', $akun->pluck('Id')->all())
            ->whereBetween('Tanggal', [$dariLalu, $saring->sampai]))
            ->groupBy('IdAkun')
            ->selectRaw(
                '`IdAkun`,'
                .' CAST(SUM(CASE WHEN `Tanggal` >= ? THEN `Kredit` - `Debit` ELSE 0 END) AS DECIMAL(20,2)) AS `Kini`,'
                .' CAST(SUM(CASE WHEN `Tanggal` <= ? THEN `Kredit` - `Debit` ELSE 0 END) AS DECIMAL(20,2)) AS `Lalu`',
                [$saring->dari, $sampaiLalu],
            )
            ->toBase()
            ->get()
            ->keyBy('IdAkun');

        $baris = [];
        $total = [];

        foreach (self::KELOMPOK as $kunci => $kelompok) {
            $kini = Uang::Nol();
            $lalu = Uang::Nol();
            $barisAkun = [];

            foreach ($akun->filter(fn (Akun $a): bool => $a->Jenis === $kelompok['Tipe']) as $satu) {
                $data = $agregat->get($satu->Id);
                // Pendapatan bertanda kredit − debit; HPP & beban dibalik agar biaya tampil positif.
                $nilai = Uang::Dari((string) ($data->Kini ?? '0'));
                $nilaiLalu = Uang::Dari((string) ($data->Lalu ?? '0'));

                if ($kunci !== 'Pendapatan') {
                    $nilai = Uang::Nol()->Kurangi($nilai);
                    $nilaiLalu = Uang::Nol()->Kurangi($nilaiLalu);
                }

                if ($nilai->BernilaiNol() && $nilaiLalu->BernilaiNol()) {
                    continue;
                }

                $kini = $kini->Tambah($nilai);
                $lalu = $lalu->Tambah($nilaiLalu);
                $barisAkun[] = self::Baris('Akun|'.$satu->Uuid, 'Akun', $kunci, $satu->Nama, $nilai, $nilaiLalu, $satu->Kode);
            }

            $total[$kunci] = ['Nilai' => $kini, 'NilaiSebelumnya' => $lalu];
            $baris[] = self::Baris('Kepala|'.$kunci, 'Kepala', $kunci, $kelompok['Label'], null, null);
            array_push($baris, ...$barisAkun);
            $baris[] = self::Baris('Subtotal|'.$kunci, 'Subtotal', $kunci, $kelompok['LabelTotal'], $kini, $lalu);

            if ($kunci === 'Hpp') {
                $baris[] = self::Baris('Laba|LabaKotor', 'Laba', 'LabaKotor', 'Laba kotor', ...self::Selisih($total, 'Pendapatan', 'Hpp'));
            }
        }

        $total['LabaKotor'] = array_combine(['Nilai', 'NilaiSebelumnya'], self::Selisih($total, 'Pendapatan', 'Hpp'));
        $total['LabaBersih'] = array_combine(['Nilai', 'NilaiSebelumnya'], self::Selisih($total, 'LabaKotor', 'Beban'));
        $baris[] = self::Baris('Laba|LabaBersih', 'Laba', 'LabaBersih', 'Laba bersih', $total['LabaBersih']['Nilai'], $total['LabaBersih']['NilaiSebelumnya']);

        return [
            'Periode' => ['Dari' => $saring->dari, 'Sampai' => $saring->sampai, 'DariSebelumnya' => $dariLalu, 'SampaiSebelumnya' => $sampaiLalu],
            'Baris' => $baris,
            'Ringkasan' => array_map(fn (array $pasangan): array => ['Nilai' => $pasangan['Nilai']->KeString(), 'NilaiSebelumnya' => $pasangan['NilaiSebelumnya']->KeString()], $total),
        ];
    }

    /**
     * Periode pembanding: bulan penuh → jumlah bulan yang sama tepat sebelumnya; selain itu jumlah hari yang sama.
     *
     * @return array{0: string, 1: string}
     */
    public static function HitungPeriodeSebelumnya(string $dari, string $sampai): array
    {
        $awal = CarbonImmutable::parse($dari)->startOfDay();
        $akhir = CarbonImmutable::parse($sampai)->startOfDay();
        $sampaiLalu = $awal->subDay();

        if ($awal->day === 1 && $akhir->isLastOfMonth()) {
            $bulan = ($akhir->year * 12 + $akhir->month) - ($awal->year * 12 + $awal->month) + 1;

            return [$awal->subMonthsNoOverflow($bulan)->toDateString(), $sampaiLalu->toDateString()];
        }

        $hari = (int) $awal->diffInDays($akhir) + 1;

        return [$sampaiLalu->subDays($hari - 1)->toDateString(), $sampaiLalu->toDateString()];
    }

    /**
     * `a − b` untuk periode ini & sebelumnya; kelompok yang belum dihitung dianggap nol.
     *
     * @param  array<string, array{Nilai: Uang, NilaiSebelumnya: Uang}>  $total
     * @return array{0: Uang, 1: Uang}
     */
    private static function Selisih(array $total, string $a, string $b): array
    {
        $nol = ['Nilai' => Uang::Nol(), 'NilaiSebelumnya' => Uang::Nol()];
        $kiri = $total[$a] ?? $nol;
        $kanan = $total[$b] ?? $nol;

        return [$kiri['Nilai']->Kurangi($kanan['Nilai']), $kiri['NilaiSebelumnya']->Kurangi($kanan['NilaiSebelumnya'])];
    }

    /**
     * @return array<string, mixed>
     */
    private static function Baris(string $id, string $jenis, string $kelompok, string $label, ?Uang $nilai, ?Uang $nilaiLalu, ?string $kode = null): array
    {
        return [
            'Id' => $id,
            'Jenis' => $jenis,
            'Kelompok' => $kelompok,
            'Kode' => $kode,
            'Label' => $label,
            'Nilai' => $nilai?->KeString(),
            'NilaiSebelumnya' => $nilaiLalu?->KeString(),
        ];
    }
}
