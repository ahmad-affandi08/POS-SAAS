<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Kueri\PemegangPeranTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * §20.2 + BR-00.8: 2FA wajib bagi Owner, Admin, dan Akuntan (peran bawaan `Peran.Kode`) di tenant yang paketnya memuat
 * fitur `keamanan.2fa-wajib` (Bisnis ke atas). Peran kustom tidak diwajibkan.
 */
final class PenentuWajibDuaFaktor
{
    /** @var list<PeranTenantBawaan> */
    public const PERAN_WAJIB = [PeranTenantBawaan::Pemilik, PeranTenantBawaan::Admin, PeranTenantBawaan::Akuntan];

    public function __construct(
        private readonly PemegangPeranTenant $pemegang,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function CekWajib(int $idPengguna, int $idTenant): bool
    {
        return $this->pemegang->CekPemegang($idPengguna, $idTenant, self::PERAN_WAJIB)
            && $this->fitur->CekAktif($idTenant, PemeriksaFiturTenant::KUNCI_2FA_WAJIB);
    }

    /**
     * Semua tenant (anggota aktif) yang mewajibkan 2FA bagi pengguna ini, apa pun tenant aktifnya.
     *
     * @return list<int>
     */
    public function AmbilTenantWajib(int $idPengguna): array
    {
        return array_values(array_filter(
            $this->pemegang->AmbilIdTenant($idPengguna, self::PERAN_WAJIB),
            fn (int $idTenant) => $this->fitur->CekAktif($idTenant, PemeriksaFiturTenant::KUNCI_2FA_WAJIB),
        ));
    }
}
