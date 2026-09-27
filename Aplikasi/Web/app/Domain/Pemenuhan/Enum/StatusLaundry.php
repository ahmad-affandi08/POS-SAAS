<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/**
 * Status tiket laundry (§9.9, F-10): proses `Diterima → Dicuci → Dikeringkan → Disetrika → Siap` boleh melompat maju
 * (misal cuci lipat tanpa setrika) tetapi tidak mundur; `Diambil` hanya dari `Siap`. `Dibatalkan` hanya lewat void
 * penjualannya. `Diambil` dan `Dibatalkan` adalah status akhir.
 */
enum StatusLaundry: string
{
    case Diterima = 'Diterima';
    case Dicuci = 'Dicuci';
    case Dikeringkan = 'Dikeringkan';
    case Disetrika = 'Disetrika';
    case Siap = 'Siap';
    case Diambil = 'Diambil';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        if ($this->CekAkhir()) {
            return false;
        }

        return match ($tujuan) {
            self::Diambil => $this === self::Siap,
            self::Dibatalkan => true,
            default => $tujuan->AmbilUrutan() > $this->AmbilUrutan(),
        };
    }

    public function CekAkhir(): bool
    {
        return $this === self::Diambil || $this === self::Dibatalkan;
    }

    /** Masih diproses (belum siap). */
    public function CekDiproses(): bool
    {
        return $this->AmbilUrutan() < self::Siap->AmbilUrutan();
    }

    public function AmbilUrutan(): int
    {
        return match ($this) {
            self::Diterima => 1,
            self::Dicuci => 2,
            self::Dikeringkan => 3,
            self::Disetrika => 4,
            self::Siap => 5,
            self::Diambil => 6,
            self::Dibatalkan => 7,
        };
    }

    /** @return list<string> */
    public static function AmbilNilaiDiproses(): array
    {
        return [self::Diterima->value, self::Dicuci->value, self::Dikeringkan->value, self::Disetrika->value];
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diterima => 'Diterima',
            self::Dicuci => 'Dicuci',
            self::Dikeringkan => 'Dikeringkan',
            self::Disetrika => 'Disetrika',
            self::Siap => 'Siap diambil',
            self::Diambil => 'Sudah diambil',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
