<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * State machine `TagihanLangganan.Status` (P-08): Draf → Terbit → Lunas; Terbit → JatuhTempo → Dihapuskan;
 * Terbit → Dibatalkan; Lunas → Dikembalikan. Keputusan agen P-08 Fase 0: tagihan yang sudah JatuhTempo tetap bisa
 * dibayar (JatuhTempo → Lunas) atau dibatalkan (JatuhTempo → Dibatalkan), karena Tertunggak → Aktif (F-00) terjadi
 * lewat pelunasan tagihan yang sudah lewat jatuh tempo.
 */
enum StatusTagihanLangganan: string
{
    case Draf = 'Draf';
    case Terbit = 'Terbit';
    case JatuhTempo = 'JatuhTempo';
    case Lunas = 'Lunas';
    case Dibatalkan = 'Dibatalkan';
    case Dihapuskan = 'Dihapuskan';
    case Dikembalikan = 'Dikembalikan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::Terbit],
            self::Terbit => [self::Lunas, self::JatuhTempo, self::Dibatalkan],
            self::JatuhTempo => [self::Lunas, self::Dihapuskan, self::Dibatalkan],
            self::Lunas => [self::Dikembalikan],
            self::Dibatalkan, self::Dihapuskan, self::Dikembalikan => [],
        }, true);
    }

    /** Tagihan yang masih menunggu pembayaran. */
    public function CekTerbuka(): bool
    {
        return $this === self::Terbit || $this === self::JatuhTempo;
    }

    /**
     * @return list<string>
     */
    public static function NilaiTerbuka(): array
    {
        return [self::Terbit->value, self::JatuhTempo->value];
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Terbit => 'Belum dibayar',
            self::JatuhTempo => 'Lewat jatuh tempo',
            self::Lunas => 'Lunas',
            self::Dibatalkan => 'Dibatalkan',
            self::Dihapuskan => 'Dihapuskan',
            self::Dikembalikan => 'Dikembalikan',
        };
    }
}
