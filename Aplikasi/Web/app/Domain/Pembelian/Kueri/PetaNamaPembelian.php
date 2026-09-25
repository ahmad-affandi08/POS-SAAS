<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pembelian\Model\Pemasok;

/**
 * Peta nama untuk daftar & detail dokumen pembelian (F-04 fase 1): pemasok (termasuk yang dihapus), lokasi stok &
 * outlet (lewat `InfoGudang`), dan nama pengguna (lewat `DaftarAnggota`), tanpa membaca tabel domain lain.
 */
final class PetaNamaPembelian
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoGudang $infoGudang,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @param  array<mixed>  $id  Id pemasok (nilai bukan int, misal null, diabaikan)
     * @return array<int, array{Uuid: string, Kode: string, Nama: string}>
     */
    public function Pemasok(array $id): array
    {
        $id = array_values(array_unique(array_filter($id, 'is_int')));

        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach (Pemasok::query()->withTrashed()->whereIn('Id', $id)->get(['Id', 'Uuid', 'Kode', 'Nama']) as $p) {
            $hasil[$p->Id] = ['Uuid' => $p->Uuid, 'Kode' => $p->Kode, 'Nama' => $p->Nama];
        }

        return $hasil;
    }

    /**
     * @param  array<mixed>  $id  Id lokasi stok (nilai bukan int diabaikan)
     * @return array<int, DataInfoGudang>
     */
    public function Gudang(array $id): array
    {
        $id = array_values(array_unique(array_filter($id, 'is_int')));

        return $id === [] ? [] : $this->infoGudang->AmbilBanyak($id);
    }

    /**
     * @param  array<mixed>  $id  Id pengguna (nilai bukan int diabaikan)
     * @return array<int, string>
     */
    public function Pengguna(array $id): array
    {
        $id = array_values(array_unique(array_filter($id, 'is_int')));

        return $id === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $id);
    }
}
