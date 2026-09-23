<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Lokasi stok aktif tenant aktif untuk domain lain (F-03 batas stok per gudang), urut nama outlet lalu nama gudang.
 */
final class GudangTenant
{
    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, NamaOutlet: string}>
     */
    public function AmbilAktif(): array
    {
        $namaOutlet = Outlet::query()->pluck('Nama', 'Id');
        $hasil = [];

        foreach (Gudang::query()->where('Status', StatusOrganisasi::Aktif->value)->orderBy('Nama')->get() as $gudang) {
            $hasil[] = [
                'Id' => $gudang->Id,
                'Uuid' => $gudang->Uuid,
                'Nama' => $gudang->Nama,
                'NamaOutlet' => (string) ($gudang->IdOutlet === null ? '' : ($namaOutlet->get($gudang->IdOutlet) ?? '')),
            ];
        }

        usort($hasil, fn (array $a, array $b): int => [$a['NamaOutlet'], $a['Nama']] <=> [$b['NamaOutlet'], $b['Nama']]);

        return $hasil;
    }
}
