<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Enum;

/**
 * Izin Platform Pengelola (PRD §19.3). Nilai memakai format permission D-06: huruf kecil, titik, kebab-case.
 * Izin flow berikutnya (P-02 dst.) ditambahkan bersama flow-nya.
 */
enum IzinPengelola: string
{
    case TimAnggotaLihat = 'tim.anggota.lihat';
    case TimAnggotaUndang = 'tim.anggota.undang';
    case TimAnggotaNonaktifkan = 'tim.anggota.nonaktifkan';
    case TimPeranTetapkan = 'tim.peran.tetapkan';
    case AuditLihat = 'audit.lihat';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::TimAnggotaLihat => 'Melihat anggota tim internal',
            self::TimAnggotaUndang => 'Mengundang anggota tim internal',
            self::TimAnggotaNonaktifkan => 'Menonaktifkan anggota tim internal',
            self::TimPeranTetapkan => 'Menetapkan peran anggota tim internal',
            self::AuditLihat => 'Melihat log audit pengelola',
        };
    }
}
