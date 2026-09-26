<?php

declare(strict_types=1);

namespace Tests\Pendukung\Organisasi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Model\Wilayah;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Model\Tenant;
use App\Http\Perantara\IdentifikasiTenantSesi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/**
 * Prasyarat F-02 untuk test: tenant terdaftar lewat F-00 (peran bawaan ikut terbentuk), anggota dengan peran
 * tertentu, dan masuk ke back-office sebagai anggota itu.
 */
final class BantuanOrganisasi
{
    private static int $urutan = 0;

    /**
     * Tenant uji yang sudah berjalan: panduan awal tidak diwajibkan (D-24). Uji aturan wajib memakai
     * `$panduanWajib = true` (sama seperti pendaftaran lewat formulir web).
     *
     * @return array{Tenant: Tenant, Pemilik: Pengguna}
     */
    public static function BuatTenant(string $namaUsaha = 'Kopi Nusantara', ?string $kodePaket = null, bool $panduanWajib = false): array
    {
        self::$urutan++;
        $slug = strtolower((string) preg_replace('/\W+/', '', $namaUsaha)).self::$urutan;
        $hasil = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data(
            email: "pemilik.{$slug}@contoh.id",
            noHp: '0812'.str_pad((string) (7000000 + self::$urutan), 8, '0', STR_PAD_LEFT),
            kodePaket: $kodePaket,
            namaUsaha: $namaUsaha,
        ), $panduanWajib);

        return ['Tenant' => $hasil['Tenant'], 'Pemilik' => $hasil['Pengguna']];
    }

    public static function Peran(int $idTenant, PeranTenantBawaan $peran): Peran
    {
        self::AturKonteks($idTenant);

        return Peran::query()->where('Kode', $peran->value)->sole();
    }

    public static function TambahAnggota(int $idTenant, PeranTenantBawaan $peran, bool $semuaOutlet = true, ?string $email = null): Pengguna
    {
        $pengguna = Pengguna::factory()->create($email === null ? [] : ['Email' => $email]);
        TenantPengguna::query()->create([
            'IdTenant' => $idTenant,
            'IdPengguna' => $pengguna->Id,
            'Pemilik' => $peran === PeranTenantBawaan::Pemilik,
            'IdPeran' => self::Peran($idTenant, $peran)->Id,
            'SemuaOutlet' => $semuaOutlet,
        ]);

        return $pengguna;
    }

    /**
     * Masuk ke back-office sebagai pengguna dengan tenant aktif tertentu. Model dimuat ulang dari database (seperti
     * saat masuk sungguhan) agar kolom seperti `TokenIngat` tersedia ketika keluar.
     */
    public static function Masuk(TestCase $tes, Pengguna $pengguna, int $idTenant): TestCase
    {
        // Sesi dikosongkan dulu seperti login sungguhan (`AuthenticateSession` menolak jejak pengguna sebelumnya).
        $tes->flushSession();

        return $tes->actingAs($pengguna->fresh() ?? $pengguna, 'web')->withSession([IdentifikasiTenantSesi::KUNCI_SESI => $idTenant]);
    }

    public static function AturKonteks(int $idTenant): void
    {
        app(KonteksTenant::class)->Atur($idTenant);
    }

    public static function BuatKota(string $kode = '33.72', string $nama = 'Kota Surakarta', ZonaWaktu $zona = ZonaWaktu::Wib): Wilayah
    {
        $kodeProvinsi = substr($kode, 0, 2);
        Wilayah::query()->firstOrCreate(['Kode' => $kodeProvinsi], [
            'Nama' => 'Provinsi '.$kodeProvinsi,
            'Tingkat' => TingkatWilayah::Provinsi,
            'ZonaWaktu' => $zona,
        ]);

        return Wilayah::query()->create([
            'Kode' => $kode,
            'Nama' => $nama,
            'Tingkat' => TingkatWilayah::KabupatenKota,
            'KodeInduk' => $kodeProvinsi,
            'ZonaWaktu' => $zona,
        ]);
    }
}
