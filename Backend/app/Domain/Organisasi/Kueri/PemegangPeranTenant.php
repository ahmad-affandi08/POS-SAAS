<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keanggotaan aktif yang memegang peran bawaan tertentu (`Peran.Kode`, §19.1), misal untuk 2FA wajib (§20.2).
 * Owner (`TenantPengguna.Pemilik`) dihitung sebagai peran Pemilik walau `IdPeran` belum terisi (tenant lama).
 * Dibaca lintas tenant milik satu pengguna, sehingga `Peran` digabung lewat join (bukan relasi ber-`MilikTenant`)
 * dan selalu disaring `IdPengguna`.
 */
final class PemegangPeranTenant
{
    /**
     * @param  list<PeranTenantBawaan>  $peran
     */
    public function CekPemegang(int $idPengguna, int $idTenant, array $peran): bool
    {
        return $this->Kueri($idPengguna, $peran)->where('TenantPengguna.IdTenant', $idTenant)->exists();
    }

    /**
     * @param  list<PeranTenantBawaan>  $peran
     * @return list<int>
     */
    public function AmbilIdTenant(int $idPengguna, array $peran): array
    {
        return array_values(array_map('intval', $this->Kueri($idPengguna, $peran)
            ->orderBy('TenantPengguna.IdTenant')
            ->pluck('TenantPengguna.IdTenant')
            ->all()));
    }

    /**
     * @param  list<PeranTenantBawaan>  $peran
     * @return Builder<TenantPengguna>
     */
    private function Kueri(int $idPengguna, array $peran): Builder
    {
        $kode = array_map(fn (PeranTenantBawaan $baris) => $baris->value, $peran);

        return TenantPengguna::query()
            ->leftJoin('Peran', 'Peran.Id', '=', 'TenantPengguna.IdPeran')
            ->where('TenantPengguna.IdPengguna', $idPengguna)
            ->where('TenantPengguna.Status', StatusKeanggotaan::Aktif->value)
            ->where(function (Builder $kueri) use ($peran, $kode): void {
                $kueri->whereIn('Peran.Kode', $kode === [] ? [''] : $kode);

                if (in_array(PeranTenantBawaan::Pemilik, $peran, true)) {
                    $kueri->orWhere('TenantPengguna.Pemilik', true);
                }
            });
    }
}
