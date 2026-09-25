<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Peta `Outlet.Id` → `Outlet.Uuid` tenant aktif untuk domain lain (F-03: `DaftarHarga.IdOutlet` disimpan sebagai Id,
 * dikirim ke POS & `PenentuHarga` sebagai Uuid). Termasuk outlet diarsipkan (daftar harga lama tetap bisa dibaca).
 * Id yang bukan milik tenant aktif tidak muncul di hasil. Juga opsi outlet (Uuid & nama) untuk form daftar harga dan
 * pemetaan balik Uuid → Id.
 */
final class PetaUuidOutlet
{
    /**
     * @param  list<int>  $idOutlet
     * @return array<int, string>
     */
    public function Ambil(array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        $hasil = [];

        foreach (Outlet::query()->whereIn('Id', array_values(array_unique($idOutlet)))->orderBy('Id')->get(['Id', 'Uuid']) as $outlet) {
            $hasil[$outlet->Id] = $outlet->Uuid;
        }

        return $hasil;
    }

    /**
     * F-04 fase 1: peta `Outlet.Id` → `Outlet.Kode` tenant aktif (nomor dokumen pembelian `PO/{OUTLET}/…`).
     *
     * @param  list<int>  $idOutlet
     * @return array<int, string>
     */
    public function AmbilKode(array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        $hasil = [];

        foreach (Outlet::query()->whereIn('Id', array_values(array_unique($idOutlet)))->get(['Id', 'Kode']) as $outlet) {
            $hasil[$outlet->Id] = (string) $outlet->Kode;
        }

        return $hasil;
    }

    /**
     * Outlet tenant aktif urut nama. `$idOutlet` null = semua; `$hanyaAktif` = tanpa outlet diarsipkan.
     *
     * @param  list<int>|null  $idOutlet
     * @return list<array{Id: int, Uuid: string, Nama: string}>
     */
    public function AmbilRingkas(?array $idOutlet = null, bool $hanyaAktif = false): array
    {
        return array_values(Outlet::query()
            ->when($idOutlet !== null, fn ($kueri) => $kueri->whereIn('Id', $idOutlet ?? []))
            ->when($hanyaAktif, fn ($kueri) => $kueri->where('Status', StatusOrganisasi::Aktif->value))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->get(['Id', 'Uuid', 'Nama'])
            ->map(fn (Outlet $outlet): array => ['Id' => $outlet->Id, 'Uuid' => $outlet->Uuid, 'Nama' => $outlet->Nama])
            ->all());
    }

    /**
     * Peta Uuid → Id outlet tenant aktif; Uuid tidak dikenal tidak muncul.
     *
     * @param  list<string>  $uuidOutlet
     * @return array<string, int>
     */
    public function AmbilIdDariUuid(array $uuidOutlet): array
    {
        if ($uuidOutlet === []) {
            return [];
        }

        $hasil = [];

        foreach (Outlet::query()->whereIn('Uuid', array_values(array_unique($uuidOutlet)))->get(['Id', 'Uuid']) as $outlet) {
            $hasil[$outlet->Uuid] = $outlet->Id;
        }

        return $hasil;
    }
}
