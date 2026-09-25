<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Kueri\BukuBesar;
use App\Domain\Akuntansi\Kueri\LabaRugi;
use App\Domain\Akuntansi\Kueri\Neraca;
use App\Domain\Akuntansi\Kueri\NeracaSaldo;
use App\Domain\Akuntansi\Layanan\PenulisCsvLaporan;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan keuangan F-13a (FIN-06, FIN-07) dari `JurnalDetail`, izin `laporan.keuangan.lihat`: buku besar per
 * akun, neraca saldo, laba rugi, dan neraca, disaring periode (`dari`, `sampai`) & outlet (`outlet`), plus ekspor CSV dengan
 * saringan yang sama. Pelaku berbatas outlet hanya menjumlah baris jurnal outlet aksesnya.
 */
final class LaporanKeuanganKontroler extends DasarAkuntansiKontroler
{
    public function BukuBesar(Request $permintaan, BukuBesar $kueri): Response|JsonResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $akun = $this->CariAkun($permintaan, $kueri);
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), [], '');
        $mutasi = fn (): array => $akun === null
            ? ['Data' => [], 'Meta' => ['Halaman' => 1, 'PerHalaman' => $tabel->perHalaman, 'Total' => 0, 'JumlahHalaman' => 1]]
            : $kueri->AmbilTabel($akun, $saring, $tabel);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Akuntansi/Laporan/BukuBesar', 'Mutasi', $mutasi, fn (): array => [
            'Saring' => [...self::PetakanSaring($saring, $permintaan), 'Akun' => $akun->Uuid ?? ''],
            'Akun' => $akun === null ? null : ['Uuid' => $akun->Uuid, 'Kode' => $akun->Kode, 'Nama' => $akun->Nama, 'SaldoNormal' => $akun->SaldoNormal->value],
            'OpsiAkun' => $kueri->AmbilOpsiAkun(),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporBukuBesar(Request $permintaan, BukuBesar $kueri): StreamedResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $akun = $this->CariAkun($permintaan, $kueri);
        abort_if($akun === null, 404);

        return PenulisCsvLaporan::Alirkan(
            'buku-besar-'.$akun->Kode.'-'.$saring->dari.'-'.$saring->sampai,
            ['Tanggal', 'Nomor jurnal', 'Keterangan', 'Sumber', 'Nomor sumber', 'Outlet', 'Debit', 'Kredit', 'Saldo'],
            (function () use ($kueri, $akun, $saring): iterable {
                foreach ($kueri->AmbilSemua($akun, $saring) as $b) {
                    yield [
                        (string) $b['Tanggal'], (string) $b['NomorJurnal'], (string) ($b['Memo'] ?? $b['Keterangan']), (string) $b['LabelSumber'],
                        is_string($b['NomorSumber']) ? $b['NomorSumber'] : '', is_string($b['NamaOutlet']) ? $b['NamaOutlet'] : '',
                        (string) $b['Debit'], (string) $b['Kredit'], (string) $b['Saldo'],
                    ];
                }
            })(),
        );
    }

    public function NeracaSaldo(Request $permintaan, NeracaSaldo $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/NeracaSaldo', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporNeracaSaldo(Request $permintaan, NeracaSaldo $kueri): StreamedResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $kolom = ['SaldoAwalDebit', 'SaldoAwalKredit', 'Debit', 'Kredit', 'SaldoAkhirDebit', 'SaldoAkhirKredit'];
        $baris = array_map(fn (array $b): array => [(string) $b['Kode'], (string) $b['Nama'], (string) $b['LabelJenis'], ...array_map(fn (string $k): string => (string) $b[$k], $kolom)], $laporan['Baris']);
        $baris[] = ['', 'Total', '', ...array_map(fn (string $k): string => $laporan['Total'][$k], $kolom)];

        return PenulisCsvLaporan::Alirkan(
            'neraca-saldo-'.$saring->dari.'-'.$saring->sampai,
            ['Kode', 'Nama akun', 'Tipe', 'Saldo awal debit', 'Saldo awal kredit', 'Debit', 'Kredit', 'Saldo akhir debit', 'Saldo akhir kredit'],
            $baris,
        );
    }

    public function LabaRugi(Request $permintaan, LabaRugi $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/LabaRugi', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporLabaRugi(Request $permintaan, LabaRugi $kueri): StreamedResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $periode = $laporan['Periode'];

        return PenulisCsvLaporan::Alirkan(
            'laba-rugi-'.$saring->dari.'-'.$saring->sampai,
            ['Kode', 'Keterangan', "{$periode['Dari']} s.d. {$periode['Sampai']}", "{$periode['DariSebelumnya']} s.d. {$periode['SampaiSebelumnya']}"],
            array_map(fn (array $b): array => [
                is_string($b['Kode']) ? $b['Kode'] : '',
                (string) $b['Label'],
                is_string($b['Nilai']) ? $b['Nilai'] : '',
                is_string($b['NilaiSebelumnya']) ? $b['NilaiSebelumnya'] : '',
            ], $laporan['Baris']),
        );
    }

    public function Neraca(Request $permintaan, Neraca $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/Neraca', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporNeraca(Request $permintaan, Neraca $kueri): StreamedResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $posisi = $laporan['Posisi'];

        return PenulisCsvLaporan::Alirkan(
            'neraca-'.$posisi['Akhir'],
            ['Kode', 'Keterangan', "Posisi {$posisi['Akhir']}", "Posisi {$posisi['Awal']}"],
            array_map(fn (array $b): array => [
                is_string($b['Kode']) ? $b['Kode'] : '',
                (string) $b['Label'],
                is_string($b['Nilai']) ? $b['Nilai'] : '',
                is_string($b['NilaiAwal']) ? $b['NilaiAwal'] : '',
            ], $laporan['Baris']),
        );
    }

    private function CariAkun(Request $permintaan, BukuBesar $kueri): ?Akun
    {
        $uuid = $permintaan->query('akun');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $akun = $kueri->CariAkun($uuid);
        abort_if($akun === null, 404);

        return $akun;
    }
}
