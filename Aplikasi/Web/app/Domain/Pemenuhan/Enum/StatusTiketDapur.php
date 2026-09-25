<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/**
 * Status tiket dapur (F-10): `Antre → Dimasak → Siap → Disajikan`. KDS hanya boleh maju satu langkah, atau mundur
 * satu langkah untuk mengoreksi salah ketuk (bukan dari `Disajikan`).
 */
enum StatusTiketDapur: string
{
    case Antre = 'Antre';
    case Dimasak = 'Dimasak';
    case Siap = 'Siap';
    case Disajikan = 'Disajikan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Antre => 'Antre',
            self::Dimasak => 'Dimasak',
            self::Siap => 'Siap',
            self::Disajikan => 'Disajikan',
        };
    }

    private function AmbilUrutan(): int
    {
        return match ($this) {
            self::Antre => 0,
            self::Dimasak => 1,
            self::Siap => 2,
            self::Disajikan => 3,
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        $selisih = $tujuan->AmbilUrutan() - $this->AmbilUrutan();

        return $selisih === 1 || ($selisih === -1 && $this !== self::Disajikan);
    }

    /** Kolom waktu yang dicatat saat tiket mencapai status ini (Antre = `DikirimPada`, diisi saat dibuat). */
    public function AmbilKolomWaktu(): ?string
    {
        return match ($this) {
            self::Antre => null,
            self::Dimasak => 'MulaiPada',
            self::Siap => 'SiapPada',
            self::Disajikan => 'DisajikanPada',
        };
    }

    public function CekLebihLanjutDari(self $lain): bool
    {
        return $this->AmbilUrutan() > $lain->AmbilUrutan();
    }

    /** Tiket yang masih tampil di KDS. */
    public function CekAktif(): bool
    {
        return $this !== self::Disajikan;
    }
}
