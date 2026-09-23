<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Model\NomorUrutTagihanLangganan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Nomor tagihan langganan `{Awalan}/{Tahun}/{Bulan}/{Urut}`, misal `INV/2026/09/000123` (BR-P08.1).
 *
 * Urut 6 digit berjalan per TAHUN tanpa celah: baris penghitung tahun dikunci `FOR UPDATE` di dalam transaksi
 * pembuatan tagihan, sehingga nomor ikut batal bila transaksi gagal. Bulan hanya penanda terbit (WIB).
 * Wajib dipanggil di dalam `DB::transaction`.
 */
final class PenomorTagihanLangganan
{
    public function Ambil(CarbonInterface $waktuTerbit): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Nomor tagihan hanya boleh diambil di dalam transaksi (BR-P08.1).');
        }

        $lokal = $waktuTerbit->copy()->setTimezone('Asia/Jakarta');
        $tahun = $lokal->year;

        // Baris tahun dibuat sekali; insertOrIgnore aman saat dua transaksi pertama di tahun itu berjalan bersamaan.
        NomorUrutTagihanLangganan::query()->insertOrIgnore([
            'Tahun' => $tahun,
            'NomorTerakhir' => 0,
            'DibuatPada' => now(),
            'DiubahPada' => now(),
        ]);

        $penghitung = NomorUrutTagihanLangganan::query()->where('Tahun', $tahun)->lockForUpdate()->firstOrFail();
        $urut = $penghitung->NomorTerakhir + 1;
        $penghitung->update(['NomorTerakhir' => $urut]);

        return sprintf('%s/%04d/%02d/%06d', (string) config('tagihan.AwalanNomor'), $tahun, $lokal->month, $urut);
    }
}
