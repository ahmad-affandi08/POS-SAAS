<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Persediaan\Enum\MetodeHpp;

/**
 * Pengaturan persediaan tingkat tenant dari `Tenant.Pengaturan` (DesainF05a C.1): metode HPP (bawaan RataRata) dan
 * izin stok minus (bawaan false, BR-05.2; `Produk.BolehMinus` menimpa per produk).
 */
final readonly class DataPengaturanPersediaan
{
    public function __construct(
        public MetodeHpp $metodeHpp,
        public bool $stokBolehMinus,
    ) {}
}
