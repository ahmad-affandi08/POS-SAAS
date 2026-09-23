<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Enum;

/**
 * State machine `TiketDukungan.Status` (P-09): Baru → Ditangani → MenungguPelanggan → Selesai → Ditutup.
 * `Selesai` bisa dibuka lagi (kembali `Ditangani`) dalam 7 hari; `Ditutup` final. Tiket yang belum selesai boleh
 * langsung ditutup (duplikat/spam).
 */
enum StatusTiketDukungan: string
{
    case Baru = 'Baru';
    case Ditangani = 'Ditangani';
    case MenungguPelanggan = 'MenungguPelanggan';
    case Selesai = 'Selesai';
    case Ditutup = 'Ditutup';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Baru => [self::Ditangani, self::MenungguPelanggan, self::Selesai, self::Ditutup],
            self::Ditangani => [self::MenungguPelanggan, self::Selesai, self::Ditutup],
            self::MenungguPelanggan => [self::Ditangani, self::Selesai, self::Ditutup],
            self::Selesai => [self::Ditangani, self::Ditutup],
            self::Ditutup => [],
        }, true);
    }

    /** Tiket yang masih perlu dikerjakan tim (antrean bawaan). */
    public function CekTerbuka(): bool
    {
        return in_array($this, [self::Baru, self::Ditangani, self::MenungguPelanggan], true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Ditangani => 'Ditangani',
            self::MenungguPelanggan => 'Menunggu pelanggan',
            self::Selesai => 'Selesai',
            self::Ditutup => 'Ditutup',
        };
    }

    /**
     * @return list<self>
     */
    public static function AmbilTerbuka(): array
    {
        return [self::Baru, self::Ditangani, self::MenungguPelanggan];
    }
}
