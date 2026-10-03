<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/**
 * Status pendaftaran wajah karyawan (F-18 bagian 4, D-37). Karyawan merekam wajah dari tautan absennya (Menunggu),
 * pengelola melihat fotonya lalu menyetujui atau menolak. Hanya wajah Disetujui yang dipakai mencocokkan absen. Ditolak
 * membuka jalan daftar ulang; reset oleh pengelola menghapus barisnya.
 */
enum StatusWajahKaryawan: string
{
    case Menunggu = 'Menunggu';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Menunggu && $tujuan !== self::Menunggu;
    }
}
