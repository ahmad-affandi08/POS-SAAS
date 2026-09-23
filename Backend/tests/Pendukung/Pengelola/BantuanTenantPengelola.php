<?php

declare(strict_types=1);

namespace Tests\Pendukung\Pengelola;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Model\Tenant;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/**
 * Bantuan test P-07 Siklus hidup tenant: tenant hasil registrasi F-00 dan anggota tim yang sedang masuk.
 */
final class BantuanTenantPengelola
{
    private static int $urutan = 0;

    /** Tenant lewat jalur registrasi asli (trial PRO 14 hari, Owner, Outlet Utama, gudang). */
    public static function BuatTenant(string $namaUsaha = 'Kopi Nusantara', ?string $email = null): Tenant
    {
        self::$urutan++;
        $email ??= 'owner'.self::$urutan.'@contoh.id';
        $noHp = '08129900'.str_pad((string) self::$urutan, 4, '0', STR_PAD_LEFT);

        return app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data($email, $noHp, namaUsaha: $namaUsaha))['Tenant'];
    }

    public static function Masuk(TestCase $tes, PeranPengelolaBawaan ...$peran): PenggunaPengelola
    {
        $anggota = BantuanPengelola::BuatAnggota(...$peran);
        $tes->actingAs($anggota, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());

        return $anggota;
    }
}
