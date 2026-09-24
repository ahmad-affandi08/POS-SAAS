<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Model\Pengguna;

/**
 * API baca publik (F-06): anggota aktif tenant dari Uuid pengguna yang punya akses ke outlet tertentu. Null bila
 * pengguna tidak dikenal, bukan anggota aktif, atau outlet di luar aksesnya.
 */
final class AnggotaOutlet
{
    public function __construct(private readonly AksesPengguna $akses) {}

    public function Cari(int $idTenant, string $uuidPengguna, int $idOutlet): ?DataAnggotaOutlet
    {
        $pengguna = Pengguna::query()->where('Uuid', $uuidPengguna)->first();

        if ($pengguna === null) {
            return null;
        }

        $akses = $this->akses->Ambil($idTenant, $pengguna->Id);
        $outlet = $this->akses->AmbilIdOutlet($idTenant, $pengguna->Id);

        if ($akses === null || ($outlet !== null && ! in_array($idOutlet, $outlet, true))) {
            return null;
        }

        return new DataAnggotaOutlet($pengguna->Id, $pengguna->Uuid, $pengguna->Nama, $akses['Pemilik'], $akses['Izin']);
    }

    /**
     * Nama pengguna per Id untuk tampilan (tanpa pemeriksaan akses).
     *
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nama: string}>
     */
    public function AmbilNama(array $id): array
    {
        $hasil = [];

        foreach (Pengguna::query()->whereIn('Id', array_values(array_unique($id)))->get(['Id', 'Uuid', 'Nama']) as $p) {
            $hasil[$p->Id] = ['Uuid' => $p->Uuid, 'Nama' => $p->Nama];
        }

        return $hasil;
    }
}
