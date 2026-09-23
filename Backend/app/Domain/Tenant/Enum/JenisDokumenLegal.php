<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Jenis dokumen legal platform (P-06, PRD §15.3). S&K dan Kebijakan Privasi wajib berlaku sebelum registrasi tenant
 * dibuka (BR-P06.2).
 */
enum JenisDokumenLegal: string
{
    case SyaratKetentuan = 'SyaratKetentuan';
    case KebijakanPrivasi = 'KebijakanPrivasi';
    case PerjanjianPemrosesanData = 'PerjanjianPemrosesanData';
    case Sla = 'Sla';
    case KontrakMitra = 'KontrakMitra';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::SyaratKetentuan => 'Syarat & Ketentuan',
            self::KebijakanPrivasi => 'Kebijakan Privasi',
            self::PerjanjianPemrosesanData => 'Perjanjian Pemrosesan Data',
            self::Sla => 'SLA per paket',
            self::KontrakMitra => 'Kontrak mitra',
        };
    }

    /**
     * @return list<self>
     */
    public static function AmbilWajibRegistrasi(): array
    {
        return [self::SyaratKetentuan, self::KebijakanPrivasi];
    }

    /**
     * BR-P06.5: versi materiil jenis ini diumumkan ke Owner dan wajib disetujui ulang setelah berlaku.
     *
     * @return list<self>
     */
    public static function AmbilWajibPersetujuanUlang(): array
    {
        return [self::SyaratKetentuan, self::KebijakanPrivasi, self::PerjanjianPemrosesanData];
    }
}
