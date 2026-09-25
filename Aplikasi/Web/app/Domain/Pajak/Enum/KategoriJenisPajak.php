<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Enum;

/**
 * Kategori jenis pajak platform (`JenisPajak.Kategori`, PRD v1.46 "Tindak lanjut tinjauan" F-07). Dipakai untuk syarat
 * profil pajak outlet (`Ppn` hanya bila outlet PKP, `Pbjt` hanya bila outlet memungut PBJT; `Lainnya` tanpa syarat)
 * dan pemetaan akun jurnal penjualan/retur, sehingga kode jenis pajak tidak dibaca sebagai string tetap.
 */
enum KategoriJenisPajak: string
{
    case Ppn = 'Ppn';
    case Pbjt = 'Pbjt';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Ppn => 'PPN',
            self::Pbjt => 'PBJT (PB1)',
            self::Lainnya => 'Pajak lain',
        };
    }
}
