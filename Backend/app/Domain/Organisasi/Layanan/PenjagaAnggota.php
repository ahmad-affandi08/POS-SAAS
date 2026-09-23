<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Aturan bersama saat pelaku mengubah anggota lain (F-02):
 * - Tidak bisa mengubah akses atau status akun sendiri (mencegah terkunci & menaikkan hak sendiri).
 * - Hanya Pemilik yang bisa mengubah Pemilik lain.
 * - Pemilik aktif terakhir tidak bisa diturunkan atau dinonaktifkan: usaha selalu punya minimal satu Pemilik.
 */
final class PenjagaAnggota
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AksesPengguna $akses,
    ) {}

    public function PastikanBolehMengubah(int $idPelaku, TenantPengguna $anggota): void
    {
        if ($anggota->IdPengguna === $idPelaku) {
            throw new PelanggaranAturanBisnis('UbahDiriSendiri', 'Anda tidak bisa mengubah akses atau status akun Anda sendiri. Minta Pemilik lain melakukannya.');
        }

        $pelaku = $this->akses->Ambil($this->konteks->Wajib(), $idPelaku);

        if ($anggota->Pemilik && ($pelaku === null || ! $pelaku['Pemilik'])) {
            throw new PelanggaranAturanBisnis('HanyaPemilik', 'Hanya Pemilik yang bisa mengubah akses atau status Pemilik lain.');
        }
    }

    public function PastikanBukanPemilikTerakhir(TenantPengguna $anggota): void
    {
        if (! $anggota->Pemilik) {
            return;
        }

        $jumlahPemilikAktif = TenantPengguna::query()
            ->where('IdTenant', $this->konteks->Wajib())
            ->where('Pemilik', true)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->lockForUpdate()
            ->count();

        if ($jumlahPemilikAktif <= 1) {
            throw new PelanggaranAturanBisnis('PemilikTerakhir', 'Ini Pemilik aktif terakhir. Tunjuk Pemilik lain dulu sebelum mengubah atau menonaktifkannya.');
        }
    }
}
