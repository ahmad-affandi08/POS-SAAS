<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Kueri\PemegangPeranTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * §20.2 + BR-00.8: 2FA wajib bagi Owner, Admin, dan Akuntan (peran bawaan `Peran.Kode`) di tenant yang paketnya memuat
 * fitur `keamanan.2fa-wajib` (Bisnis ke atas). Peran kustom tidak diwajibkan.
 *
 * D-38: selama langganan masih **Trial**, kewajiban ditunda ([CekDitunda]) — back-office tetap terbuka dan hanya
 * menampilkan banner pengingat. Begitu trial berakhir (berbayar/Gratis), pengalihan ke Keamanan akun berlaku lagi.
 */
final class PenentuWajibDuaFaktor
{
    /** @var list<PeranTenantBawaan> */
    public const PERAN_WAJIB = [PeranTenantBawaan::Pemilik, PeranTenantBawaan::Admin, PeranTenantBawaan::Akuntan];

    public function __construct(
        private readonly PemegangPeranTenant $pemegang,
        private readonly PemeriksaFiturTenant $fitur,
        private readonly StatusLanggananTenant $statusLangganan,
    ) {}

    /** D-38: kewajiban 2FA ditunda selama masa trial tenant. */
    public function CekDitunda(int $idTenant): bool
    {
        return $this->statusLangganan->Ambil($idTenant) === StatusLangganan::Trial;
    }

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
