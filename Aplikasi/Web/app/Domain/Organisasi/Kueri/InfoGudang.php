<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ringkasan lokasi stok tenant aktif untuk domain lain (DesainF05a C.1): per Id, per Uuid, yang boleh diakses
 * pengguna, dan pencocokan kunci impor (Kode lalu Nama, tanpa membedakan huruf besar/kecil). Lokasi stok diarsipkan
 * ikut dikembalikan (`aktif = false`) kecuali di `AmbilBoleh(hanyaAktif: true)`; pemanggil yang memutuskan.
 */
final class InfoGudang
{
    private const UKURAN_POTONGAN = 1000;

    /**
     * @param  list<int>  $id
     * @return array<int, DataInfoGudang> kunci = Id
     */
    public function AmbilBanyak(array $id): array
    {
        $hasil = [];

        foreach ($this->AmbilGudang('Id', array_values(array_unique($id))) as $data) {
            $hasil[$data->id] = $data;
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $uuid
     * @return array<string, DataInfoGudang> kunci = Uuid
     */
    public function AmbilDariUuid(array $uuid): array
    {
        $hasil = [];

        foreach ($this->AmbilGudang('Uuid', array_values(array_unique($uuid))) as $data) {
            $hasil[$data->uuid] = $data;
        }

        return $hasil;
    }

    /**
     * Lokasi stok di outlet yang boleh diakses (null = semua lokasi, termasuk yang tanpa outlet), urut nama outlet
     * lalu nama lokasi.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<DataInfoGudang>
     */
    public function AmbilBoleh(?array $idOutletBoleh, bool $hanyaAktif = true): array
    {
        $gudang = Gudang::query()
            ->when($hanyaAktif, fn ($kueri) => $kueri->where('Status', StatusOrganisasi::Aktif->value))
            ->when($idOutletBoleh !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->get();

        $hasil = $this->Petakan($gudang);
        usort($hasil, fn (DataInfoGudang $a, DataInfoGudang $b): int => [(string) $a->namaOutlet, $a->nama, $a->id] <=> [(string) $b->namaOutlet, $b->nama, $b->id]);

        return $hasil;
    }

    /**
     * Pencocokan kolom "Lokasi Stok" impor: Kode dulu, lalu Nama (keduanya tanpa membedakan huruf besar/kecil, spasi
     * tepi diabaikan). Lebih dari satu Id = ambigu; daftar kosong = tidak dikenal.
     *
     * @param  list<string>  $kunci
     * @return array<string, list<int>> kunci = teks masukan apa adanya
     */
    public function CariKunciImpor(array $kunci): array
    {
        $semua = Gudang::query()->orderBy('Id')->get(['Id', 'Kode', 'Nama']);
        $perKode = [];
        $perNama = [];

        foreach ($semua as $gudang) {
            $perKode[mb_strtolower(trim($gudang->Kode))][] = $gudang->Id;
            $perNama[mb_strtolower(trim($gudang->Nama))][] = $gudang->Id;
        }

        $hasil = [];

        foreach ($kunci as $teks) {
            $normal = mb_strtolower(trim($teks));
            $hasil[$teks] = $normal === '' ? [] : ($perKode[$normal] ?? $perNama[$normal] ?? []);
        }

        return $hasil;
    }

    /**
     * @param  list<int|string>  $nilai
     * @return list<DataInfoGudang>
     */
    private function AmbilGudang(string $kolom, array $nilai): array
    {
        $hasil = [];

        foreach (array_chunk($nilai, self::UKURAN_POTONGAN) as $potongan) {
            $hasil = [...$hasil, ...$this->Petakan(Gudang::query()->whereIn($kolom, $potongan)->orderBy('Id')->get())];
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, Gudang>  $gudang
     * @return list<DataInfoGudang>
     */
    private function Petakan(Collection $gudang): array
    {
        $idOutlet = array_values(array_unique(array_filter($gudang->pluck('IdOutlet')->all(), 'is_int')));
        $namaOutlet = $idOutlet === [] ? [] : Outlet::query()->whereIn('Id', $idOutlet)->pluck('Nama', 'Id')->all();

        return array_values($gudang->map(fn (Gudang $g): DataInfoGudang => new DataInfoGudang(
            $g->Id,
            $g->Uuid,
            $g->Kode,
            $g->Nama,
            $g->Jenis,
            $g->IdOutlet,
            $g->IdOutlet === null ? null : (isset($namaOutlet[$g->IdOutlet]) ? (string) $namaOutlet[$g->IdOutlet] : null),
            $g->Status === StatusOrganisasi::Aktif,
        ))->all());
    }
}
