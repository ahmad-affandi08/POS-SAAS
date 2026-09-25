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
 * Neraca / laporan posisi keuangan (F-13, FIN-07 P1, tipe FE `PropsNeraca`) dari `JurnalDetail`: posisi pada akhir
 * periode (`sampai`) dibanding posisi awal periode (sehari sebelum `dari`). Aset = debit − kredit; kewajiban &
 * ekuitas = kredit − debit (akun kontra tampil negatif di kelompoknya). Tutup buku (FIN-08) belum ada, jadi saldo akun
 * pendapatan/HPP/beban masih terbuka: laba kumulatifnya ditampilkan di ekuitas sebagai "Laba tahun-tahun lalu" (sebelum
 * 1 Januari tahun posisi) dan "Laba tahun berjalan". Karena setiap jurnal seimbang, total aset = total kewajiban +
 * ekuitas (`Seimbang`); saringan outlet bisa membuatnya tidak seimbang bila satu jurnal memuat baris beberapa outlet.
 * Satu kueri agregat per akun untuk kedua posisi (DECIMAL, eksak).
 */
final class Neraca
{
    private const KELOMPOK = [
        'Aset' => ['Tipe' => TipeAkun::Aset, 'Label' => 'Aset', 'LabelTotal' => 'Total aset'],
        'Kewajiban' => ['Tipe' => TipeAkun::Kewajiban, 'Label' => 'Kewajiban', 'LabelTotal' => 'Total kewajiban'],
        'Ekuitas' => ['Tipe' => TipeAkun::Ekuitas, 'Label' => 'Ekuitas', 'LabelTotal' => 'Total ekuitas'],
    ];

    private const TIPE_LABA_RUGI = [TipeAkun::Pendapatan, TipeAkun::Hpp, TipeAkun::Beban];

    /**
     * @return array{Posisi: array{Akhir: string, Awal: string}, Baris: list<array<string, mixed>>, Ringkasan: array<string, array{Nilai: string, NilaiAwal: string}>, Seimbang: bool}
     */
    public function Ambil(SaringLaporanKeuangan $saring): array
    {
        $akhir = $saring->sampai;
        $awal = CarbonImmutable::parse($saring->dari)->subDay()->toDateString();
        $awalTahunAkhir = CarbonImmutable::parse($akhir)->startOfYear()->toDateString();
        $awalTahunAwal = CarbonImmutable::parse($awal)->startOfYear()->toDateString();

        // Saldo debit − kredit per akun pada kedua posisi, plus bagian sebelum 1 Januari tiap posisi (untuk laba).
        $agregat = $saring->TerapkanOutlet(JurnalDetail::query()->where('Tanggal', '<=', $akhir))
            ->groupBy('IdAkun')
            ->selectRaw(
                '`IdAkun`,'
                .' CAST(SUM(`Debit` - `Kredit`) AS DECIMAL(20,2)) AS `Akhir`,'
                .' CAST(SUM(CASE WHEN `Tanggal` <= ? THEN `Debit` - `Kredit` ELSE 0 END) AS DECIMAL(20,2)) AS `Awal`,'
                .' CAST(SUM(CASE WHEN `Tanggal` < ? THEN `Debit` - `Kredit` ELSE 0 END) AS DECIMAL(20,2)) AS `AkhirTahunLalu`,'
                .' CAST(SUM(CASE WHEN `Tanggal` < ? THEN `Debit` - `Kredit` ELSE 0 END) AS DECIMAL(20,2)) AS `AwalTahunLalu`',
                [$awal, $awalTahunAkhir, $awalTahunAwal],
            )
            ->toBase()
            ->get()
            ->keyBy('IdAkun');

        $akun = Akun::query()->whereKey($agregat->keys()->map(fn ($id): int => (int) $id)->all())->orderBy('Kode')->get();
        $ambil = fn (int $id, string $kolom): Uang => Uang::Dari((string) ($agregat->get($id)->{$kolom} ?? '0'));

        // Laba kumulatif = −(debit − kredit) seluruh akun pendapatan, HPP, beban.
        $labaLalu = ['Nilai' => Uang::Nol(), 'NilaiAwal' => Uang::Nol()];
        $labaBerjalan = ['Nilai' => Uang::Nol(), 'NilaiAwal' => Uang::Nol()];

        foreach ($akun->filter(fn (Akun $a): bool => in_array($a->Jenis, self::TIPE_LABA_RUGI, true)) as $satu) {
            $labaLalu['Nilai'] = $labaLalu['Nilai']->Kurangi($ambil($satu->Id, 'AkhirTahunLalu'));
            $labaLalu['NilaiAwal'] = $labaLalu['NilaiAwal']->Kurangi($ambil($satu->Id, 'AwalTahunLalu'));
            $labaBerjalan['Nilai'] = $labaBerjalan['Nilai']->Kurangi($ambil($satu->Id, 'Akhir')->Kurangi($ambil($satu->Id, 'AkhirTahunLalu')));
            $labaBerjalan['NilaiAwal'] = $labaBerjalan['NilaiAwal']->Kurangi($ambil($satu->Id, 'Awal')->Kurangi($ambil($satu->Id, 'AwalTahunLalu')));
        }

        $baris = [];
        $total = [];

        foreach (self::KELOMPOK as $kunci => $kelompok) {
            $nilai = Uang::Nol();
            $nilaiAwal = Uang::Nol();
            $barisAkun = [];

            foreach ($akun->filter(fn (Akun $a): bool => $a->Jenis === $kelompok['Tipe']) as $satu) {
                $kini = $ambil($satu->Id, 'Akhir');
                $dulu = $ambil($satu->Id, 'Awal');

                // Kewajiban & ekuitas bersaldo normal kredit: tampil kredit − debit.
                if ($kunci !== 'Aset') {
                    $kini = Uang::Nol()->Kurangi($kini);
                    $dulu = Uang::Nol()->Kurangi($dulu);
                }

                if ($kini->BernilaiNol() && $dulu->BernilaiNol()) {
                    continue;
                }

                $nilai = $nilai->Tambah($kini);
                $nilaiAwal = $nilaiAwal->Tambah($dulu);
                $barisAkun[] = self::Baris('Akun|'.$satu->Uuid, 'Akun', $kunci, $satu->Nama, $kini, $dulu, $satu->Kode);
            }

            if ($kunci === 'Ekuitas') {
                foreach ([['LabaLalu', 'Laba tahun-tahun lalu (belum ditutup buku)', $labaLalu], ['LabaBerjalan', 'Laba tahun berjalan', $labaBerjalan]] as [$id, $label, $laba]) {
                    if ($id === 'LabaLalu' && $laba['Nilai']->BernilaiNol() && $laba['NilaiAwal']->BernilaiNol()) {
                        continue;
                    }

                    $nilai = $nilai->Tambah($laba['Nilai']);
                    $nilaiAwal = $nilaiAwal->Tambah($laba['NilaiAwal']);
                    $barisAkun[] = self::Baris('Laba|'.$id, 'Laba', $kunci, $label, $laba['Nilai'], $laba['NilaiAwal']);
                }
            }

            $total[$kunci] = ['Nilai' => $nilai, 'NilaiAwal' => $nilaiAwal];
            $baris[] = self::Baris('Kepala|'.$kunci, 'Kepala', $kunci, $kelompok['Label'], null, null);
            array_push($baris, ...$barisAkun);
            $baris[] = self::Baris('Subtotal|'.$kunci, 'Subtotal', $kunci, $kelompok['LabelTotal'], $nilai, $nilaiAwal);
        }

        $total['KewajibanEkuitas'] = [
            'Nilai' => $total['Kewajiban']['Nilai']->Tambah($total['Ekuitas']['Nilai']),
            'NilaiAwal' => $total['Kewajiban']['NilaiAwal']->Tambah($total['Ekuitas']['NilaiAwal']),
        ];
        $total['LabaBerjalan'] = $labaBerjalan;
        $baris[] = self::Baris('Total|KewajibanEkuitas', 'Total', 'KewajibanEkuitas', 'Total kewajiban dan ekuitas', $total['KewajibanEkuitas']['Nilai'], $total['KewajibanEkuitas']['NilaiAwal']);

        return [
            'Posisi' => ['Akhir' => $akhir, 'Awal' => $awal],
            'Baris' => $baris,
            'Ringkasan' => array_map(fn (array $p): array => ['Nilai' => $p['Nilai']->KeString(), 'NilaiAwal' => $p['NilaiAwal']->KeString()], $total),
            'Seimbang' => $total['Aset']['Nilai']->SamaDengan($total['KewajibanEkuitas']['Nilai'])
                && $total['Aset']['NilaiAwal']->SamaDengan($total['KewajibanEkuitas']['NilaiAwal']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function Baris(string $id, string $jenis, string $kelompok, string $label, ?Uang $nilai, ?Uang $nilaiAwal, ?string $kode = null): array
    {
        return [
            'Id' => $id,
            'Jenis' => $jenis,
            'Kelompok' => $kelompok,
            'Kode' => $kode,
            'Label' => $label,
            'Nilai' => $nilai?->KeString(),
            'NilaiAwal' => $nilaiAwal?->KeString(),
        ];
    }
}
