<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPemilik;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Bantuan bersama kontroler data Aplikasi Owner (OWN-02/05/08): pengguna dari token, tenant aktif dari `X-Tenant`,
 * dan outlet yang boleh diakses (hak akses sama dengan back-office, §19.1). Outlet di luar akses atau tidak dikenal
 * diperlakukan sebagai tidak ada (404 `OutletTidakDitemukan`).
 */
abstract class DasarPemilikKontroler extends Kontroler
{
    protected function Pengguna(Request $permintaan): Pengguna
    {
        return AutentikasiPemilik::AmbilPengguna($permintaan);
    }

    protected function IdTenant(): int
    {
        return app(KonteksTenant::class)->Wajib();
    }

    protected function CekIzin(Request $permintaan, IzinTenant $izin): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pengguna($permintaan)->Id, $izin);
    }

    /**
     * Outlet yang boleh diakses pengguna. Null = semua outlet.
     *
     * @return list<int>|null
     */
    protected function IdOutletBoleh(Request $permintaan): ?array
    {
        return app(AksesPengguna::class)->AmbilIdOutlet($this->IdTenant(), $this->Pengguna($permintaan)->Id);
    }

    /**
     * Outlet yang dihitung: outlet terpilih (`?outlet=`), atau semua outlet yang boleh diakses.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<int>|null
     */
    protected function SaringOutlet(?string $uuidOutlet, ?array $idOutletBoleh): ?array
    {
        if ($uuidOutlet === null || $uuidOutlet === '') {
            return $idOutletBoleh;
        }

        $id = app(PetaUuidOutlet::class)->AmbilIdDariUuid([$uuidOutlet])[$uuidOutlet] ?? null;

        if ($id === null || ($idOutletBoleh !== null && ! in_array($id, $idOutletBoleh, true))) {
            throw new PelanggaranAturanBisnis('OutletTidakDitemukan', 'Outlet tidak ditemukan atau di luar akses Anda.', 'outlet', 404);
        }

        return [$id];
    }

    /**
     * Tanggal bisnis hari ini (satu outlet: zona & jam tutup buku outlet itu; selain itu zona waktu tenant).
     *
     * @param  list<int>|null  $idOutlet
     */
    protected function TanggalHariIni(?array $idOutlet): CarbonImmutable
    {
        return app(TanggalBisnisOutlet::class)->Hitung($idOutlet !== null && count($idOutlet) === 1 ? $idOutlet[0] : null);
    }

    /**
     * @param  list<int>|null  $idOutlet
     */
    protected function BacaTanggal(?string $tanggal, ?array $idOutlet): CarbonImmutable
    {
        $hasil = $tanggal === null || $tanggal === '' ? false : CarbonImmutable::createFromFormat('!Y-m-d', $tanggal);

        return $hasil instanceof CarbonImmutable ? $hasil : $this->TanggalHariIni($idOutlet);
    }
}
