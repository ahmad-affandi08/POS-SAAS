<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Kueri\SumberFiturTenant;

/**
 * Layanan publik untuk domain & lapisan HTTP lain: apakah satu fitur paket aktif untuk tenant (P-04, BR-P04.7).
 */
final class PemeriksaFiturTenant
{
    /** §20.2: 2FA wajib untuk Owner/Admin di paket Bisnis ke atas. */
    public const KUNCI_2FA_WAJIB = 'keamanan.2fa-wajib';

    public function __construct(
        private readonly SumberFiturTenant $sumber,
        private readonly EvaluatorFitur $evaluator,
    ) {}

    public function CekAktif(int $idTenant, string $kunci): bool
    {
        return $this->evaluator->CekFiturAktif($this->sumber->Ambil($idTenant), $kunci);
    }
}
