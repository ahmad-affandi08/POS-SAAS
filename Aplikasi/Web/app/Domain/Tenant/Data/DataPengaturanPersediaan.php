<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\MetodeHpp;

/**
 * Pengaturan persediaan tingkat tenant dari `Tenant.Pengaturan` (DesainF05a C.1): metode HPP (bawaan RataRata) dan
 * izin stok minus (bawaan false, BR-05.2; `Produk.BolehMinus` menimpa per produk). F-05b: `batasPersetujuanPenyesuaian`
 * (bawaan Rp 500.000, §19.2): penyesuaian stok bernilai di atasnya butuh persetujuan orang lain.
 */
final readonly class DataPengaturanPersediaan
{
    public const BATAS_PERSETUJUAN_PENYESUAIAN_BAWAAN = '500000.00';

    public Uang $batasPersetujuanPenyesuaian;

    public function __construct(
        public MetodeHpp $metodeHpp,
        public bool $stokBolehMinus,
        ?Uang $batasPersetujuanPenyesuaian = null,
    ) {
        $this->batasPersetujuanPenyesuaian = $batasPersetujuanPenyesuaian ?? Uang::Dari(self::BATAS_PERSETUJUAN_PENYESUAIAN_BAWAAN);
    }
}
