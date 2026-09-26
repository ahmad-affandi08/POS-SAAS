<?php

declare(strict_types=1);

namespace Tests\Pendukung\Penjualan;

use App\Domain\Integrasi\Aksi\SimpanGerbangPembayaranTenant;
use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use Illuminate\Support\Str;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;

/**
 * Gerbang pembayaran QRIS dinamis milik tenant (v2.06) untuk test: langsung aktif & lolos uji (jalur simpan → uji →
 * aktifkan diuji terpisah di `GerbangPembayaranTenantTes`).
 */
final class BantuanGerbangTenant
{
    /**
     * @param  array<string, string>  $kredensial
     * @param  array<string, string|int>  $pengaturan
     */
    public static function Aktifkan(
        int $idTenant,
        PenyediaGerbang $penyedia = PenyediaGerbang::Midtrans,
        array $kredensial = ['KunciServer' => 'SB-Mid-server-UjiKunciRahasia123'],
        array $pengaturan = ['Akuisitor' => 'gopay'],
    ): GerbangPembayaranTenant {
        BantuanOrganisasi::AturKonteks($idTenant);

        return GerbangPembayaranTenant::query()->create([
            'Uuid' => (string) Str::ulid(),
            'Penyedia' => $penyedia,
            'Lingkungan' => LingkunganGerbang::Sandbox,
            'Pengaturan' => $pengaturan,
            'Kredensial' => $kredensial,
            'PetunjukKredensial' => array_map(SimpanGerbangPembayaranTenant::BuatPetunjuk(...), $kredensial),
            'StatusUji' => StatusUjiGerbang::Berhasil,
            'Aktif' => true,
            'TokenWebhook' => SimpanGerbangPembayaranTenant::BuatToken($idTenant),
        ]);
    }

    public static function Nonaktifkan(int $idTenant): void
    {
        BantuanOrganisasi::AturKonteks($idTenant);
        GerbangPembayaranTenant::query()->update(['Aktif' => false]);
    }
}
