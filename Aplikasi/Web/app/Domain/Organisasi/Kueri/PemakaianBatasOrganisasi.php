<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Organisasi\Model\UndanganPengguna;

/**
 * Pemakaian yang dibatasi paket (BR-02.1, BR-P04.3):
 * - Outlet: outlet berstatus Aktif (outlet diarsipkan tidak dihitung).
 * - Pengguna: anggota aktif + undangan yang masih berlaku (kursi yang sudah dijanjikan ikut dihitung, agar
 *   undangan tidak bisa melampaui batas saat diterima bersamaan).
 */
final class PemakaianBatasOrganisasi
{
    /** `kunci`: kunci baris outlet aktif (hanya di dalam transaksi), misal saat menjaga minimal satu outlet aktif. */
    public function HitungOutlet(bool $kunci = false): int
    {
        return Outlet::query()->where('Status', StatusOrganisasi::Aktif->value)->when($kunci, fn ($kueri) => $kueri->lockForUpdate())->count();
    }

    public function HitungPengguna(int $idTenant, ?int $kecualiIdUndangan = null, ?string $kecualiEmail = null): int
    {
        $anggota = TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->count();

        $undangan = UndanganPengguna::query()
            ->where('IdTenant', $idTenant)
            ->whereNull('DiterimaPada')
            ->whereNull('DibatalkanPada')
            ->where('BerlakuSampai', '>', now())
            ->when($kecualiIdUndangan !== null, fn ($kueri) => $kueri->whereKeyNot($kecualiIdUndangan))
            ->when($kecualiEmail !== null, fn ($kueri) => $kueri->where('Email', '!=', $kecualiEmail))
            ->count();

        return $anggota + $undangan;
    }
}
