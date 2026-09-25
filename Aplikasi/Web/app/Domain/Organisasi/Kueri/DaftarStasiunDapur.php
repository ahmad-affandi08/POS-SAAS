<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\StasiunDapur;

/**
 * Kueri publik stasiun dapur tenant (F-10a) untuk domain lain (Katalog: pilihan stasiun kategori; PanduanAwal:
 * penerapan template) dan halaman back-office. Stasiun bawaan = stasiun aktif pertama menurut Urutan lalu Nama.
 */
final class DaftarStasiunDapur
{
    /**
     * @return list<array{Uuid: string, Nama: string, Urutan: int, Status: string}>
     */
    public function AmbilSemua(): array
    {
        return array_values(StasiunDapur::query()->orderBy('Status')->orderBy('Urutan')->orderBy('Nama')->get()
            ->map(fn (StasiunDapur $s): array => ['Uuid' => $s->Uuid, 'Nama' => $s->Nama, 'Urutan' => $s->Urutan, 'Status' => $s->Status->value])
            ->all());
    }

    /**
     * Pilihan stasiun aktif untuk form kategori.
     *
     * @return list<array{Nilai: string, Label: string}>
     */
    public function AmbilOpsiAktif(): array
    {
        return array_values(StasiunDapur::query()->where('Status', StatusOrganisasi::Aktif->value)->orderBy('Urutan')->orderBy('Nama')->get()
            ->map(fn (StasiunDapur $s): array => ['Nilai' => $s->Uuid, 'Label' => $s->Nama])
            ->all());
    }

    /** Id stasiun aktif dari Uuid; null bila tidak ada, milik tenant lain, atau diarsipkan. */
    public function CariIdAktif(string $uuid): ?int
    {
        $id = StasiunDapur::query()->where('Uuid', $uuid)->where('Status', StatusOrganisasi::Aktif->value)->value('Id');

        return is_int($id) ? $id : null;
    }

    /**
     * Semua stasiun (termasuk yang diarsipkan) per Id, untuk menampilkan rujukan kategori.
     *
     * @return array<int, array{Uuid: string, Nama: string, Aktif: bool}>
     */
    public function AmbilPetaId(): array
    {
        $hasil = [];

        foreach (StasiunDapur::query()->get() as $s) {
            $hasil[$s->Id] = ['Uuid' => $s->Uuid, 'Nama' => $s->Nama, 'Aktif' => $s->Status === StatusOrganisasi::Aktif];
        }

        return $hasil;
    }
}
