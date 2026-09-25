<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\AreaMeja;
use App\Domain\Organisasi\Model\Meja;

/**
 * Area & meja satu outlet untuk back-office dan (nanti) paket data aplikasi kasir mode meja (F-10a). Urut: status
 * (aktif dulu), urutan, lalu nama alami ("2" sebelum "10").
 */
final class MejaOutlet
{
    /**
     * @return array{Area: list<array{Uuid: string, Nama: string, Urutan: int, Status: string, JumlahMeja: int}>, Meja: list<array{Uuid: string, Nama: string, UuidArea: string|null, NamaArea: string|null, Kapasitas: int, Bentuk: string, Urutan: int, Status: string}>}
     */
    public function Ambil(int $idOutlet): array
    {
        $area = AreaMeja::query()->where('IdOutlet', $idOutlet)->orderBy('Status')->orderBy('Urutan')->orderBy('Nama')->get();
        $meja = Meja::query()->where('IdOutlet', $idOutlet)->get()
            ->sort(fn (Meja $a, Meja $b): int => [$a->Status->value, $a->Urutan] <=> [$b->Status->value, $b->Urutan] ?: strnatcasecmp($a->Nama, $b->Nama))
            ->values();
        $perArea = $area->keyBy('Id');
        $jumlah = $meja->filter(fn (Meja $m): bool => $m->Status === StatusOrganisasi::Aktif && $m->IdAreaMeja !== null)
            ->countBy(fn (Meja $m): int => (int) $m->IdAreaMeja);

        return [
            'Area' => array_values($area->map(fn (AreaMeja $a): array => [
                'Uuid' => $a->Uuid,
                'Nama' => $a->Nama,
                'Urutan' => $a->Urutan,
                'Status' => $a->Status->value,
                'JumlahMeja' => (int) ($jumlah->get($a->Id) ?? 0),
            ])->all()),
            'Meja' => array_values($meja->map(function (Meja $m) use ($perArea): array {
                $area = $m->IdAreaMeja === null ? null : $perArea->get($m->IdAreaMeja);

                return [
                    'Uuid' => $m->Uuid,
                    'Nama' => $m->Nama,
                    'UuidArea' => $area?->Uuid,
                    'NamaArea' => $area?->Nama,
                    'Kapasitas' => $m->Kapasitas,
                    'Bentuk' => $m->Bentuk->value,
                    'Urutan' => $m->Urutan,
                    'Status' => $m->Status->value,
                ];
            })->all()),
        ];
    }

    /**
     * Meja aktif di outlet (untuk membuka/memindah pesanan); null bila tidak ada, outlet lain, atau diarsipkan.
     *
     * @return array{Id: int, Nama: string}|null
     */
    public function CariAktif(int $idOutlet, string $uuid): ?array
    {
        $meja = Meja::query()->where('IdOutlet', $idOutlet)->where('Uuid', $uuid)->where('Status', StatusOrganisasi::Aktif->value)->first();

        return $meja === null ? null : ['Id' => $meja->Id, 'Nama' => $meja->Nama];
    }

    /**
     * Uuid & nama meja per Id (termasuk yang diarsipkan) untuk menampilkan pesanan.
     *
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nama: string}>
     */
    public function AmbilPerId(array $id): array
    {
        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach (Meja::query()->whereKey($id)->get(['Id', 'Uuid', 'Nama']) as $m) {
            $hasil[$m->Id] = ['Uuid' => $m->Uuid, 'Nama' => $m->Nama];
        }

        return $hasil;
    }
}
