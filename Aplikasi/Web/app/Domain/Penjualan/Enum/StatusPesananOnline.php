<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

enum StatusPesananOnline: string
{
    case MenungguPembayaran = 'MenungguPembayaran';
    case MenungguKonfirmasi = 'MenungguKonfirmasi';
    case Dikonfirmasi = 'Dikonfirmasi';
    case Diproses = 'Diproses';
    case Siap = 'Siap';
    case Selesai = 'Selesai';
    case Ditolak = 'Ditolak';
    case Dibatalkan = 'Dibatalkan';
    case Kedaluwarsa = 'Kedaluwarsa';

    /**
     * Status akhir: pesanan tidak lagi menahan jatah "pesanan aktif" pelanggan dan tidak bisa berpindah lagi.
     *
     * @return list<string>
     */
    public static function NilaiFinal(): array
    {
        return [self::Selesai->value, self::Ditolak->value, self::Dibatalkan->value, self::Kedaluwarsa->value];
    }

    /**
     * Status yang boleh dipilih staf di back-office. `Kedaluwarsa` hanya dipasang jadwal
     * `pesanan-online:kedaluwarsa`, dan `MenungguKonfirmasi` adalah status awal, bukan tujuan.
     *
     * @return list<self>
     */
    public static function PilihanStaf(): array
    {
        return [self::Dikonfirmasi, self::Diproses, self::Siap, self::Selesai, self::Ditolak, self::Dibatalkan];
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::MenungguKonfirmasi => 'Menunggu konfirmasi',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            // Pembayaran belum masuk: staf belum melihat pesanannya, jadi hanya pelanggan/jadwal yang mengakhirinya.
            self::MenungguPembayaran => [self::MenungguKonfirmasi, self::Dibatalkan, self::Kedaluwarsa],
            self::MenungguKonfirmasi => [self::Dikonfirmasi, self::Ditolak, self::Dibatalkan, self::Kedaluwarsa],
            self::Dikonfirmasi => [self::Diproses, self::Dibatalkan],
            self::Diproses => [self::Siap, self::Dibatalkan],
            self::Siap => [self::Selesai, self::Dibatalkan],
            self::Selesai, self::Ditolak, self::Dibatalkan, self::Kedaluwarsa => [],
        }, true);
    }
}
