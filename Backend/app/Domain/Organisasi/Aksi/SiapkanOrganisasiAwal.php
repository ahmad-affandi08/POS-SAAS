<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Organisasi bawaan tenant baru (F-00 langkah 3): satu merek, "Outlet Utama", dan gudang tokonya.
 */
final class SiapkanOrganisasiAwal
{
    public const KODE_OUTLET_UTAMA = 'UTAMA';

    public function Jalankan(int $idTenant, string $namaUsaha, string $zonaWaktu): Outlet
    {
        $merek = Merek::query()->create(['IdTenant' => $idTenant, 'Nama' => $namaUsaha]);
        $outlet = Outlet::query()->create([
            'IdTenant' => $idTenant,
            'IdMerek' => $merek->Id,
            'Kode' => self::KODE_OUTLET_UTAMA,
            'Nama' => 'Outlet Utama',
            'ZonaWaktu' => $zonaWaktu,
        ]);
        Gudang::query()->create([
            'IdTenant' => $idTenant,
            'IdOutlet' => $outlet->Id,
            'Kode' => self::KODE_OUTLET_UTAMA,
            'Nama' => 'Gudang Outlet Utama',
            'Jenis' => JenisGudang::Toko,
        ]);

        return $outlet;
    }
}
