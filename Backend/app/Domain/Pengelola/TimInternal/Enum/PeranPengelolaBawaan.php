<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Enum;

/**
 * Tujuh peran internal bawaan (P-01 langkah 2, PRD §19.3). Nilai = kolom `PeranPengelola.Kode`.
 *
 * Pada P-01 hanya Super Admin yang mengelola tim & melihat log audit ("semua menu pengelola").
 * Izin peran lain ditambahkan bersama flow yang menjadi cakupannya (P-02 s.d. P-12).
 */
enum PeranPengelolaBawaan: string
{
    case SuperAdmin = 'SuperAdmin';
    case Keuangan = 'Keuangan';
    case Dukungan = 'Dukungan';
    case Teknis = 'Teknis';
    case KontenLegal = 'KontenLegal';
    case MitraPenjualan = 'MitraPenjualan';
    case Analis = 'Analis';

    public function Nama(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Keuangan => 'Keuangan',
            self::Dukungan => 'Dukungan',
            self::Teknis => 'Teknis',
            self::KontenLegal => 'Konten & Legal',
            self::MitraPenjualan => 'Mitra & Penjualan',
            self::Analis => 'Analis',
        };
    }

    /**
     * @return list<IzinPengelola>
     */
    public function Izin(): array
    {
        return match ($this) {
            self::SuperAdmin => IzinPengelola::cases(),
            default => [],
        };
    }
}
