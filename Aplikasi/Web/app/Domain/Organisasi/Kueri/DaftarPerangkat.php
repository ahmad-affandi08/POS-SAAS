<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Perangkat;

/**
 * Daftar perangkat di tenant aktif untuk halaman Perangkat back-office (F-02b), dibatasi outlet yang boleh diakses
 * pelaku. Perangkat aktif & belum diaktifkan lebih dulu, yang dicabut di akhir.
 */
final class DaftarPerangkat
{
    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return list<array<string, mixed>>
     */
    public function Ambil(?array $idOutletBoleh): array
    {
        $outlet = Outlet::query()->get(['Id', 'Uuid', 'Kode', 'Nama'])->keyBy('Id');

        return array_values(Perangkat::query()
            ->when($idOutletBoleh !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->orderByRaw('DicabutPada IS NOT NULL')
            ->orderBy('Kode')
            ->get()
            ->map(fn (Perangkat $perangkat): array => [
                'Uuid' => $perangkat->Uuid,
                'Kode' => $perangkat->Kode,
                'Nama' => $perangkat->Nama,
                'Jenis' => $perangkat->Jenis->value,
                'LabelJenis' => $perangkat->Jenis->AmbilLabel(),
                'UuidOutlet' => $outlet->get($perangkat->IdOutlet)?->Uuid,
                'NamaOutlet' => $outlet->get($perangkat->IdOutlet)?->Nama,
                'Status' => $perangkat->AmbilStatus(),
                'Platform' => $perangkat->Platform?->value,
                'VersiAplikasi' => $perangkat->VersiAplikasi,
                'DiaktifkanPada' => $perangkat->DiaktifkanPada?->toIso8601String(),
                'TerakhirAktifPada' => $perangkat->TerakhirAktifPada?->toIso8601String(),
                'DicabutPada' => $perangkat->DicabutPada?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Label perangkat per Id untuk tampilan domain lain (F-06 shift): `Kode — Nama`.
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function AmbilLabel(array $id): array
    {
        $hasil = [];

        foreach (Perangkat::query()->whereIn('Id', array_values(array_unique($id)))->get(['Id', 'Kode', 'Nama']) as $perangkat) {
            $hasil[$perangkat->Id] = "{$perangkat->Kode} — {$perangkat->Nama}";
        }

        return $hasil;
    }

    /** Perangkat tenant aktif berdasarkan Uuid di satu outlet; null bila tidak ada (termasuk milik tenant/outlet lain). */
    public function CariDiOutlet(string $uuid, int $idOutlet): ?Perangkat
    {
        return Perangkat::query()->where('Uuid', $uuid)->where('IdOutlet', $idOutlet)->first();
    }
}
