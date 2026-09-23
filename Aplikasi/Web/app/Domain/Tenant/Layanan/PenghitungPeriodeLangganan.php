<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Enum\JenisTagihanLangganan;
use App\Domain\Tenant\Enum\SiklusTagihan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Periode layanan dari tagihan yang lunas (P-08 langkah 3 "periode diperpanjang"). Murni, tanpa kueri.
 *
 * - Perpanjangan menyambung dari akhir periode berjalan (termasuk saat Tertunggak, sehingga masa tenggang tidak
 *   menjadi hari gratis). Bila akhir periode sudah tidak diketahui, mulai saat pembayaran diterima.
 * - Aktivasi dimulai saat pembayaran diterima.
 * - Akhir periode = mulai + 1 bulan / 1 tahun tanpa melompati akhir bulan (31 Jan + 1 bulan = 28/29 Feb).
 */
final class PenghitungPeriodeLangganan
{
    public static function JumlahBulan(SiklusTagihan $siklus): int
    {
        return match ($siklus) {
            SiklusTagihan::Bulanan => 1,
            SiklusTagihan::Tahunan => 12,
        };
    }

    /**
     * @return array{Mulai: CarbonImmutable, Selesai: CarbonImmutable}
     */
    public function Hitung(JenisTagihanLangganan $jenis, SiklusTagihan $siklus, CarbonInterface $diterimaPada, ?CarbonInterface $periodeSelesaiBerjalan): array
    {
        $mulai = $jenis === JenisTagihanLangganan::Perpanjangan && $periodeSelesaiBerjalan !== null
            ? CarbonImmutable::instance($periodeSelesaiBerjalan)
            : CarbonImmutable::instance($diterimaPada);

        return ['Mulai' => $mulai, 'Selesai' => $mulai->addMonthsNoOverflow(self::JumlahBulan($siklus))];
    }
}
