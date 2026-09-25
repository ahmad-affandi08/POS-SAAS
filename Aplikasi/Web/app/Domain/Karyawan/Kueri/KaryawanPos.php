<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\Karyawan;

/**
 * Staf yang bisa dipilih sebagai pelayan baris penjualan di POS (F-18, data awal `Karyawan`): karyawan aktif
 * berpangkalan di outlet ini atau tanpa outlet utama, urut nama.
 */
final class KaryawanPos
{
    /**
     * @return list<array{Uuid: string, Nama: string, Jabatan: string|null}>
     */
    public function Ambil(int $idOutlet): array
    {
        return array_values(Karyawan::query()
            ->where('Status', StatusKaryawan::Aktif->value)
            ->where(fn ($k) => $k->whereNull('IdOutlet')->orWhere('IdOutlet', $idOutlet))
            ->orderBy('Nama')
            ->get(['Uuid', 'Nama', 'Jabatan'])
            ->map(fn (Karyawan $k): array => ['Uuid' => $k->Uuid, 'Nama' => $k->Nama, 'Jabatan' => $k->Jabatan])
            ->all());
    }
}
