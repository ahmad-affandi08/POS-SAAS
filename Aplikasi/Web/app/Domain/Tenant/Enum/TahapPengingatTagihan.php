<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

use Carbon\CarbonInterface;

/**
 * Tahap pengingat tagihan langganan (P-08 langkah 4 / F-19 dunning): H-7, H-3, H0, H+3 terhadap tanggal jatuh tempo
 * (tanggal kalender WIB). Tahap yang terlewat (penjadwal mati sehari) tidak dikirim berurutan: hanya tahap terbaru
 * yang sudah berlaku, supaya Owner tidak menerima beberapa email sekaligus.
 */
enum TahapPengingatTagihan: string
{
    case HMinus7 = 'HMinus7';
    case HMinus3 = 'HMinus3';
    case HariH = 'HariH';
    case HPlus3 = 'HPlus3';

    /** Selisih hari (hari ini − jatuh tempo) saat tahap ini mulai berlaku. */
    public function AmbilHari(): int
    {
        return match ($this) {
            self::HMinus7 => -7,
            self::HMinus3 => -3,
            self::HariH => 0,
            self::HPlus3 => 3,
        };
    }

    public function AmbilUrutan(): int
    {
        return match ($this) {
            self::HMinus7 => 1,
            self::HMinus3 => 2,
            self::HariH => 3,
            self::HPlus3 => 4,
        };
    }

    /** Tahap terbaru yang berlaku pada `$sekarang`; null = belum H-7. */
    public static function Tentukan(CarbonInterface $sekarang, CarbonInterface $jatuhTempo): ?self
    {
        $hariIni = $sekarang->copy()->setTimezone('Asia/Jakarta')->startOfDay();
        $tanggalJatuhTempo = $jatuhTempo->copy()->setTimezone('Asia/Jakarta')->startOfDay();
        $selisih = (int) $tanggalJatuhTempo->diffInDays($hariIni, false);
        $hasil = null;

        foreach (self::cases() as $tahap) {
            if ($selisih >= $tahap->AmbilHari()) {
                $hasil = $tahap;
            }
        }

        return $hasil;
    }

    /**
     * Tahap yang lebih awal dari tahap ini (dipakai klaim atomik: hanya naik, tidak pernah mengulang).
     *
     * @return list<string>
     */
    public function AmbilNilaiSebelumnya(): array
    {
        return array_values(array_map(
            static fn (self $tahap): string => $tahap->value,
            array_filter(self::cases(), fn (self $tahap): bool => $tahap->AmbilUrutan() < $this->AmbilUrutan()),
        ));
    }
}
