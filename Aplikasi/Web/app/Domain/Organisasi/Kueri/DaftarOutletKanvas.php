<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Modul Salesman bagian 3 (§9.7): outlet kanvas tenant aktif (kendaraan salesman) untuk halaman Grosir › Kanvas,
 * beserta lokasi stok Toko-nya (= bak kendaraan; aktif didahulukan, lalu Id terkecil). Urut: aktif dulu, lalu nama.
 */
final class DaftarOutletKanvas
{
    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return list<array{Id: int, Uuid: string, Kode: string, Nama: string, NomorKendaraan: string|null, Status: string, ZonaWaktu: string, IdGudang: int|null, UuidGudang: string|null, NamaGudang: string|null}>
     */
    public function Ambil(?array $idOutletBoleh): array
    {
        if ($idOutletBoleh === []) {
            return [];
        }

        $outlet = Outlet::query()
            ->where('Kanvas', true)
            ->when($idOutletBoleh !== null, fn ($kueri) => $kueri->whereKey($idOutletBoleh ?? []))
            ->orderByRaw('CASE WHEN `Status` = ? THEN 0 ELSE 1 END', [StatusOrganisasi::Aktif->value])
            ->orderBy('Nama')
            ->orderBy('Id')
            ->get();

        if ($outlet->isEmpty()) {
            return [];
        }

        $gudang = [];

        foreach (Gudang::query()
            ->whereIn('IdOutlet', $outlet->modelKeys())
            ->where('Jenis', JenisGudang::Toko->value)
            ->orderByRaw('CASE WHEN `Status` = ? THEN 0 ELSE 1 END', [StatusOrganisasi::Aktif->value])
            ->orderBy('Id')
            ->get(['Id', 'Uuid', 'Nama', 'IdOutlet']) as $g) {
            $gudang[(int) $g->IdOutlet] ??= $g;
        }

        return array_values($outlet->map(function (Outlet $o) use ($gudang): array {
            $g = $gudang[$o->Id] ?? null;

            return [
                'Id' => $o->Id,
                'Uuid' => $o->Uuid,
                'Kode' => $o->Kode,
                'Nama' => $o->Nama,
                'NomorKendaraan' => $o->NomorKendaraan,
                'Status' => $o->Status->value,
                'ZonaWaktu' => $o->ZonaWaktu,
                'IdGudang' => $g?->Id,
                'UuidGudang' => $g?->Uuid,
                'NamaGudang' => $g?->Nama,
            ];
        })->all());
    }
}
