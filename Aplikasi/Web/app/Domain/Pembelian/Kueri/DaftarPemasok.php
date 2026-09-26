<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use Illuminate\Support\Collection;

/**
 * Daftar pemasok untuk `TabelData` (F-04 fase 1; tipe FE `BarisPemasok`), bawaan urut nama. Cari: kode, nama, kontak,
 * no. HP, NPWP. Saring: `Status` (Aktif/Nonaktif). Juga opsi pemasok untuk formulir dokumen.
 */
final class DaftarPemasok
{
    public const KOLOM_URUT = ['Kode', 'Nama', 'TerminHari'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = 'Nama';

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', ['Aktif', 'Nonaktif']);
        $pola = PenerapKueriTabel::PolaCari($permintaan->cari);

        $kueri = Pemasok::query()
            ->when(count($status) === 1, fn ($kueri) => $kueri->where('Aktif', $status[0] === 'Aktif'))
            ->when($permintaan->cari !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Kode', 'like', $pola)
                ->orWhere('Nama', 'like', $pola)
                ->orWhere('NamaKontak', 'like', $pola)
                ->orWhere('NoHp', 'like', $pola)
                ->orWhere('Npwp', 'like', $pola)));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Kode' => 'Kode', 'Nama' => 'Nama', 'TerminHari' => 'TerminHari'], fn (Collection $baris): array => array_values($baris->map(fn (Pemasok $p): array => self::Petakan($p))->all()));
    }

    /**
     * Opsi pemasok aktif (plus `sertakan` bila nonaktif) untuk formulir, urut nama.
     *
     * @return list<array{Uuid: string, Kode: string, Nama: string, Pkp: bool, TerminHari: int, Aktif: bool}>
     */
    public function AmbilPilihan(?int $sertakan = null): array
    {
        return array_values(Pemasok::query()
            ->where(fn ($kueri) => $kueri->where('Aktif', true)->when($sertakan !== null, fn ($atau) => $atau->orWhere('Id', $sertakan)))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->get()
            ->map(fn (Pemasok $p): array => ['Uuid' => $p->Uuid, 'Kode' => $p->Kode, 'Nama' => $p->Nama, 'Pkp' => $p->Pkp, 'TerminHari' => $p->TerminHari, 'Aktif' => $p->Aktif])
            ->all());
    }

    /** F-16c bagian 4b: Id pemasok aktif dari Uuid (null bila tidak ada, nonaktif, atau tenant lain). */
    public function CariIdAktif(string $uuid): ?int
    {
        $id = Pemasok::query()->where('Uuid', $uuid)->where('Aktif', true)->value('Id');

        return is_int($id) ? $id : null;
    }

    /** F-16c bagian 4b: Id pemasok (termasuk nonaktif) dari Uuid; null bila tidak ada atau tenant lain. */
    public function AmbilIdDariUuid(string $uuid): ?int
    {
        $id = Pemasok::query()->where('Uuid', $uuid)->value('Id');

        return is_int($id) ? $id : null;
    }

    /**
     * F-16c bagian 4b: Uuid & nama pemasok per Id (termasuk nonaktif), untuk menampilkan rujukan.
     *
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nama: string}>
     */
    public function AmbilRingkas(array $id): array
    {
        return $id === [] ? [] : Pemasok::query()->whereKey($id)->get(['Id', 'Uuid', 'Nama'])
            ->mapWithKeys(fn (Pemasok $p): array => [$p->Id => ['Uuid' => $p->Uuid, 'Nama' => $p->Nama]])->all();
    }

    /**
     * F-16c bagian 4e: sisa hutang terbuka per pemasok (faktur belum lunas, tanpa belanja stok) yang bisa dipotong klaim.
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function AmbilSisaHutang(array $id): array
    {
        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach ($id as $i) {
            $hasil[$i] = Uang::Nol();
        }

        FakturPembelian::query()->whereIn('IdPemasok', $id)->where('BelanjaStok', false)
            ->whereIn('Status', [StatusFakturPembelian::BelumDibayar->value, StatusFakturPembelian::DibayarSebagian->value])
            ->get(['Id', 'IdPemasok', 'Total', 'JumlahDibayar', 'JumlahRetur'])
            ->each(function (FakturPembelian $f) use (&$hasil): void {
                $hasil[$f->IdPemasok] = $hasil[$f->IdPemasok]->Tambah($f->AmbilSisa());
            });

        $keluar = [];

        foreach ($id as $i) {
            $keluar[$i] = $hasil[$i]->KeString();
        }

        return $keluar;
    }

    /**
     * @return array<string, mixed>
     */
    public static function Petakan(Pemasok $p): array
    {
        return [
            'Uuid' => $p->Uuid,
            'Kode' => $p->Kode,
            'Nama' => $p->Nama,
            'NamaKontak' => $p->NamaKontak,
            'NoHp' => $p->NoHp,
            'Email' => $p->Email,
            'Alamat' => $p->Alamat,
            'Npwp' => $p->Npwp,
            'Pkp' => $p->Pkp,
            'TerminHari' => $p->TerminHari,
            'NamaBank' => $p->NamaBank,
            'NomorRekening' => $p->NomorRekening,
            'AtasNamaRekening' => $p->AtasNamaRekening,
            'Catatan' => $p->Catatan,
            'Aktif' => $p->Aktif,
        ];
    }
}
