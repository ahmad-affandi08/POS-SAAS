<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Enum;

/**
 * Alasan tangguhkan manual oleh Super Admin (P-07, BR-P07.4). Kategori dikirim ke Owner; catatan rinci tetap internal.
 */
enum KategoriPenangguhan: string
{
    case Penipuan = 'Penipuan';
    case Penyalahgunaan = 'Penyalahgunaan';
    case PermintaanHukum = 'PermintaanHukum';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Penipuan => 'Dugaan penipuan',
            self::Penyalahgunaan => 'Penyalahgunaan layanan',
            self::PermintaanHukum => 'Permintaan aparat/hukum',
            self::Lainnya => 'Lainnya',
        };
    }
}
