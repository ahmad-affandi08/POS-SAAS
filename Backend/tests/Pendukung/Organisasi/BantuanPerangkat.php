<?php

declare(strict_types=1);

namespace Tests\Pendukung\Organisasi;

use App\Domain\Organisasi\Aksi\BuatPerangkat;
use App\Domain\Organisasi\Enum\JenisPerangkat;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prasyarat F-02b untuk test: perangkat + kode aktivasi (lewat Aksi), aktivasi lewat API POS, PIN anggota, dan
 * status langganan.
 */
final class BantuanPerangkat
{
    /**
     * @return array{Perangkat: Perangkat, Kode: string}
     */
    public static function BuatPerangkat(int $idTenant, ?Outlet $outlet = null, JenisPerangkat $jenis = JenisPerangkat::Kasir, string $nama = 'Kasir Depan'): array
    {
        BantuanOrganisasi::AturKonteks($idTenant);
        $outlet ??= Outlet::query()->orderBy('Id')->firstOrFail();
        $hasil = app(BuatPerangkat::class)->Jalankan($outlet, $nama, $jenis, null);

        return ['Perangkat' => $hasil['Perangkat'], 'Kode' => $hasil['Kode']];
    }

    /**
     * Tukar kode lewat API POS dan kembalikan token perangkat.
     */
    public static function Aktifkan(TestCase $tes, string $kode, string $platform = 'Android'): string
    {
        $respons = $tes->postJson('/api/pos/v1/perangkat/aktivasi', ['Kode' => $kode, 'Platform' => $platform, 'VersiAplikasi' => '1.0.0'])->assertCreated();
        $token = $respons->json('TokenPerangkat');

        return is_string($token) ? $token : '';
    }

    /**
     * @return array{Perangkat: Perangkat, Token: string}
     */
    public static function BuatDanAktifkan(TestCase $tes, int $idTenant, ?Outlet $outlet = null, string $nama = 'Kasir Depan'): array
    {
        ['Perangkat' => $perangkat, 'Kode' => $kode] = self::BuatPerangkat($idTenant, $outlet, nama: $nama);

        return ['Perangkat' => $perangkat, 'Token' => self::Aktifkan($tes, $kode)];
    }

    public static function AturPin(int $idTenant, int $idPengguna, string $pin): void
    {
        TenantPengguna::query()->where('IdTenant', $idTenant)->where('IdPengguna', $idPengguna)->update(['HashPin' => Hash::make($pin)]);
    }

    public static function AturStatusLangganan(int $idTenant, StatusLangganan $status): void
    {
        Langganan::query()->where('IdTenant', $idTenant)->update(['Status' => $status->value]);
    }
}
