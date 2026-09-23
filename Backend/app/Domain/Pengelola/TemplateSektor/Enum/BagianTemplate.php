<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Enum;

/**
 * Pembagian isi template per penanggung jawab (BR-P03.5): isi bisnis oleh Konten & Legal, akun & pajak oleh Keuangan.
 */
enum BagianTemplate: string
{
    case IsiBisnis = 'IsiBisnis';
    case Akun = 'Akun';

    /**
     * @return list<string> Kunci `TemplateSektorVersi.Isi` yang menjadi milik bagian ini.
     */
    public function AmbilKunciIsi(): array
    {
        return match ($this) {
            self::IsiBisnis => [
                'ModeKasir', 'ModeKasirDefault', 'KunciFitur', 'Kategori', 'KodeSatuan', 'Pengaturan',
                'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian', 'LaporanUnggulan',
            ],
            self::Akun => ['Akun', 'PemetaanAkun', 'KelompokPajak'],
        };
    }

    public function AmbilAksiAudit(): string
    {
        return match ($this) {
            self::IsiBisnis => 'template.isi.ubah',
            self::Akun => 'template.akun.ubah',
        };
    }
}
