<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Kueri\RingkasanTenant;

/**
 * OWN-01: profil Aplikasi Owner, yaitu akun pengguna dan tenant tempat ia menjadi anggota aktif (urut nama), dengan
 * tanda apakah ia pemilik tenant itu. Bentuk sama untuk `/masuk`, `/masuk/dua-faktor`, dan `/profil`.
 */
final class ProfilPemilik
{
    public function __construct(
        private readonly KeanggotaanPengguna $keanggotaan,
        private readonly RingkasanTenant $tenant,
    ) {}

    /**
     * @return array{Pengguna: array{Uuid: string, Nama: string, Email: string}, Tenant: list<array{Uuid: string, Nama: string, Pemilik: bool}>}
     */
    public function Ambil(Pengguna $pengguna): array
    {
        $pemilik = $this->keanggotaan->AmbilTandaPemilik($pengguna->Id);

        return [
            'Pengguna' => ['Uuid' => $pengguna->Uuid, 'Nama' => $pengguna->Nama, 'Email' => $pengguna->Email],
            'Tenant' => array_map(fn (array $t): array => [
                'Uuid' => $t['Uuid'],
                'Nama' => $t['Nama'],
                'Pemilik' => $pemilik[$t['Id']] ?? false,
            ], $this->tenant->Ambil(array_keys($pemilik))),
        ];
    }

    /** Id tenant dari Uuid bila pengguna anggota aktifnya; null = bukan anggota atau tidak dikenal (sama saja). */
    public function CariIdTenant(Pengguna $pengguna, string $uuidTenant): ?int
    {
        foreach ($this->tenant->Ambil($this->keanggotaan->AmbilIdTenant($pengguna->Id)) as $t) {
            if ($t['Uuid'] === $uuidTenant) {
                return $t['Id'];
            }
        }

        return null;
    }
}
