<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Asal dokumen stok awal (DesainF05a B.4): diisi manual di form atau dibuat impor Excel/CSV (selalu Draf).
 */
enum SumberStokAwal: string
{
    case Manual = 'Manual';
    case Impor = 'Impor';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Impor => 'Impor berkas',
        };
    }
}
