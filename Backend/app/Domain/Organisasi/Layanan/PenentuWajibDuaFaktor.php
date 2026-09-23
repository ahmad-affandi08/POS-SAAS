<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Kueri\PemilikTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * §20.2 + BR-00.8: 2FA wajib bagi Owner tenant yang paketnya memuat fitur `keamanan.2fa-wajib` (Bisnis ke atas).
 *
 * TODO F-02: peran Admin (dan Akuntan, §20.2) tenant ikut diwajibkan setelah peran & izin tenant tersedia.
 */
final class PenentuWajibDuaFaktor
{
    public function __construct(
        private readonly PemilikTenant $pemilik,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function CekWajib(int $idPengguna, int $idTenant): bool
    {
        return $this->pemilik->CekPemilik($idPengguna, $idTenant)
            && $this->fitur->CekAktif($idTenant, PemeriksaFiturTenant::KUNCI_2FA_WAJIB);
    }
}
