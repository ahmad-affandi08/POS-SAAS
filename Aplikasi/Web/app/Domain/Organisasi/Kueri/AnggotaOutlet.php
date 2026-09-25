<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

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
     * F-07b (PRD v1.46): anggota tenant dari Uuid pengguna beserta tanda apakah ia (masih) punya akses ke outlet ini.
     * Anggota yang sudah dinonaktifkan tetap dikembalikan (tanpa izin, tanpa akses outlet) karena transaksi offline-nya
     * bisa terjadi sebelum dinonaktifkan. Null hanya bila pengguna tidak dikenal atau tidak pernah menjadi anggota
     * tenant; pemanggil memutuskan apakah izin/akses yang hilang ditolak atau ditandai untuk ditinjau.
     *
     * @return array{0: DataAnggotaOutlet, 1: bool}|null [anggota, punya akses outlet]
     */
    public function CariDiTenant(int $idTenant, string $uuidPengguna, int $idOutlet): ?array
    {
        $pengguna = Pengguna::query()->where('Uuid', $uuidPengguna)->first();

        if ($pengguna === null) {
            return null;
        }

        $akses = $this->akses->Ambil($idTenant, $pengguna->Id);

        if ($akses === null) {
            $pernahAnggota = TenantPengguna::query()->where('IdTenant', $idTenant)->where('IdPengguna', $pengguna->Id)->exists();

            return $pernahAnggota ? [new DataAnggotaOutlet($pengguna->Id, $pengguna->Uuid, $pengguna->Nama, false, []), false] : null;
        }

        $outlet = $this->akses->AmbilIdOutlet($idTenant, $pengguna->Id);

        return [
            new DataAnggotaOutlet($pengguna->Id, $pengguna->Uuid, $pengguna->Nama, $akses['Pemilik'], $akses['Izin']),
            $outlet === null || in_array($idOutlet, $outlet, true),
        ];
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

    /**
     * Id anggota tenant yang namanya memuat kata cari (pencarian `TabelData` domain lain, D-16). Maks 200 Id.
     *
     * @return list<int>
     */
    public function CariIdDariNama(int $idTenant, string $kata): array
    {
        $idAnggota = TenantPengguna::query()->where('IdTenant', $idTenant)->select('IdPengguna');

        return array_values(array_map('intval', Pengguna::query()
            ->whereIn('Id', $idAnggota)
            ->where('Nama', 'like', '%'.addcslashes($kata, '\\%_').'%')
            ->limit(200)
            ->pluck('Id')
            ->all()));
    }
}
