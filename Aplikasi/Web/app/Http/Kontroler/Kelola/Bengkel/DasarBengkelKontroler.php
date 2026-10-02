<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Bengkel;

use App\Domain\Bengkel\Kueri\DaftarPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Carbon\CarbonImmutable;

/**
 * Bantuan bersama kontroler bengkel (§9.10): perintah kerja lewat Uuid di dalam scope tenant dengan batas outlet
 * pelaku (di luar akses = 404), opsi outlet/mekanik, zona & hari bisnis tenant. Izin rute dijaga `WajibIzinTenant`.
 */
abstract class DasarBengkelKontroler extends DasarKelolaKontroler
{
    protected function CariPerintahKerja(string $uuid): PerintahKerja
    {
        $pk = app(DaftarPerintahKerja::class)->Cari($uuid, $this->IdOutletBoleh());
        abort_if($pk === null, 404);

        return $pk;
    }

    protected function ZonaTenant(): string
    {
        return (string) app(ProfilTenant::class)->Ambil($this->IdTenant())['ZonaWaktu'];
    }

    protected function HariIni(): CarbonImmutable
    {
        return app(TanggalBisnisOutlet::class)->Hitung(null);
    }

    protected function SlugTenant(): string
    {
        return app(ProfilTenant::class)->AmbilSlug($this->IdTenant());
    }

    protected function CekIzin(IzinTenant $izin): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, $izin);
    }

    /**
     * @return list<array{Uuid: string, Nama: string}>
     */
    protected function OpsiOutlet(): array
    {
        return array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], app(PetaUuidOutlet::class)->AmbilRingkas($this->IdOutletBoleh(), hanyaAktif: true));
    }

    /**
     * @return list<array{Uuid: string, Nama: string}>
     */
    protected function OpsiMekanik(): array
    {
        return app(JadwalStafReservasi::class)->AmbilOpsi();
    }
}
