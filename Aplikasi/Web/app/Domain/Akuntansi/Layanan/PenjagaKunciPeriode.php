<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Menolak transaksi bertanggal di periode terkunci (`PeriodeTerkunci`, §11, DesainF05a C.5). Dipanggil jurnal dan
 * buku stok (H-9) di dalam transaksi posting: pembacaan memakai kunci bersama agar penguncian periode yang berjalan
 * bersamaan tidak terlewat. Titik perluasan pengecualian "review Akuntan" F-15.
 */
final class PenjagaKunciPeriode
{
    private const NAMA_BULAN = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    /**
     * @throws PelanggaranAturanBisnis `PeriodeTerkunci` bila periode tanggal ini sudah dikunci
     */
    public function PastikanTerbuka(CarbonInterface $tanggal): void
    {
        $periode = $tanggal->format('Y-m');

        if (! $this->CariTerkunci($periode, true)) {
            return;
        }

        throw new PelanggaranAturanBisnis(
            'PeriodeTerkunci',
            'Periode '.self::FormatPeriode($periode).' sudah dikunci. Pakai tanggal di periode yang masih terbuka, atau minta Akuntan membuka kunci periode.',
            'Tanggal',
            detail: ['Periode' => $periode],
        );
    }

    /**
     * @param  string  $periode  `YYYY-MM`
     *
     * @throws InvalidArgumentException bila periode tidak berformat `YYYY-MM`
     */
    public function CekTerkunci(string $periode): bool
    {
        return $this->CariTerkunci($periode, false);
    }

    /** "2026-09" → "September 2026". */
    public static function FormatPeriode(string $periode): string
    {
        [$tahun, $bulan] = explode('-', $periode) + ['', ''];

        return trim((self::NAMA_BULAN[$bulan] ?? $bulan).' '.$tahun);
    }

    private function CariTerkunci(string $periode, bool $kunciBaca): bool
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new InvalidArgumentException("Periode {$periode} harus berformat YYYY-MM.");
        }

        return KunciPeriode::query()
            ->where('Periode', $periode)
            ->when($kunciBaca, fn ($kueri) => $kueri->sharedLock())
            ->first(['Id']) !== null;
    }
}
