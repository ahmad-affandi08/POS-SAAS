<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
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
        if (! $konteks->CekIzin('akuntansi.kelola') || $konteks->hariIni->day < self::TANGGAL_PENGINGAT) {
            return [];
        }

        $bulanLalu = $konteks->hariIni->startOfMonth()->subMonth();
        $periode = $bulanLalu->format('Y-m');
        $terkunci = KunciPeriode::query()->where('Periode', $periode)->whereNotNull('DikunciPada')->exists();
        $adaJurnal = ! $terkunci && Jurnal::query()->where('Periode', $periode)->exists();

        return [new DataButirTindakan(
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
