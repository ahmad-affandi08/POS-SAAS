<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Owner (pemilik) tenant: dipakai penegakan 2FA wajib (§20.2) dan persetujuan ulang dokumen legal (BR-P06.5).
 * Hanya keanggotaan berstatus Aktif yang dihitung.
 *
 * TODO F-02: peran Admin tenant ikut diwajibkan 2FA setelah peran & izin tenant tersedia.
 */
final class PemilikTenant
{
    public function CekPemilik(int $idPengguna, int $idTenant): bool
    {
        return TenantPengguna::query()
            ->where('IdPengguna', $idPengguna)
            ->where('IdTenant', $idTenant)
            ->where('Pemilik', true)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->exists();
    }

    /**
     * Semua pengguna yang menjadi Owner aktif setidaknya di satu tenant, sekali per pengguna.
     *
     * @return iterable<array{Id: int, Nama: string, Email: string}>
     */
    public function AmbilSemua(): iterable
    {
        $pemilik = TenantPengguna::query()
            ->select('IdPengguna')
            ->where('Pemilik', true)
            ->where('Status', StatusKeanggotaan::Aktif->value);

        foreach (Pengguna::query()->whereIn('Id', $pemilik)->lazyById(200, 'Id') as $pengguna) {
            yield ['Id' => $pengguna->Id, 'Nama' => $pengguna->Nama, 'Email' => $pengguna->Email];
        }
    }
}
