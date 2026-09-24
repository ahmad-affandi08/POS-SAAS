<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Pengaturan kasir tingkat tenant dari `Tenant.Pengaturan` (F-06, PRD v1.34): batas kas keluar tanpa persetujuan
 * (BR-06.4, bawaan Rp 200.000 sesuai §19.2) dan mode shift bersama (BR-06.2, bawaan mati).
 */
final readonly class DataPengaturanKasir
{
    public const BATAS_KAS_KELUAR_BAWAAN = '200000.00';

    public function __construct(
        public Uang $batasKasKeluar,
        public bool $shiftBersama,
    ) {}
}
