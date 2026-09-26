<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;

/**
 * Kotak Tindakan domain Akuntansi (D-23 C, izin `akuntansi.kelola`): mulai tanggal [TANGGAL_PENGINGAT] setiap bulan,
 * bulan lalu yang sudah ada jurnalnya tetapi belum ditutup buku (F-15). Selesai sendiri saat periodenya dikunci.
 */
final class PenyediaTindakanAkuntansi implements PenyediaTindakan
{
    public const TANGGAL_PENGINGAT = 10;

    private const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('akuntansi.kelola')) {
            return [];
        }

        // D-23 D: transaksi kas & bank berulang yang ditolak saat dicatat otomatis (misal periode terkunci).
        $gagal = JadwalKasBank::query()->where('Aktif', true)->whereNotNull('GalatTerakhir')->orderBy('Id')->get();
        $butir = [new DataButirTindakan(
            'kas-bank.berulang-gagal',
            'Keuangan',
            TingkatTindakan::Penting,
            'Transaksi rutin gagal dicatat otomatis',
            'Perbaiki penyebabnya (misal buka kunci periode atau aktifkan akun), atau hentikan jadwalnya.',
            $gagal->count(),
            '/kelola/akuntansi/kas-bank/berulang',
            'Lihat jadwal',
            array_values($gagal->take(DataButirTindakan::BATAS_RINCIAN)->map(fn (JadwalKasBank $j): DataRincianTindakan => new DataRincianTindakan(
                $j->Uuid,
                $j->Keterangan,
                (string) $j->GalatTerakhir,
                $j->TanggalBerikutnya->toDateString(),
                null,
            ))->all()),
        )];

        if ($konteks->hariIni->day < self::TANGGAL_PENGINGAT) {
            return $butir;
        }

        $bulanLalu = $konteks->hariIni->startOfMonth()->subMonth();
        $periode = $bulanLalu->format('Y-m');
        $terkunci = KunciPeriode::query()->where('Periode', $periode)->whereNotNull('DikunciPada')->exists();
        $adaJurnal = ! $terkunci && Jurnal::query()->where('Periode', $periode)->exists();

        return [...$butir, new DataButirTindakan(
            'periode.belum-ditutup',
            'Keuangan',
            TingkatTindakan::Perhatian,
            'Tutup buku '.self::BULAN[$bulanLalu->month - 1].' '.$bulanLalu->year,
            'Bulan lalu belum ditutup buku. Setelah dicek, tutup agar laporan keuangan tidak berubah lagi.',
            $adaJurnal ? 1 : 0,
            '/kelola/akuntansi/tutup-buku',
            'Tutup buku',
        )];
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
