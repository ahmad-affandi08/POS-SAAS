<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Kueri;

use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\KelompokPajak;
use App\Domain\Pajak\Model\KelompokPajakDetail;
use Carbon\CarbonImmutable;

/**
 * Kelompok pajak tenant aktif (F-01 langkah 3 & 4; F-03 opsi form produk & impor) dan data jenis pajak platform
 * untuk domain lain.
 */
final class DaftarKelompokPajak
{
    /**
     * @return list<array{Nama: string, Pajak: list<array{KodeJenisPajak: string, NamaJenisPajak: string, DasarPengenaan: string, LabelDasarPengenaan: string}>}>
     */
    public function Ambil(): array
    {
        return array_values(KelompokPajak::query()->with('Detail.JenisPajak')->orderBy('Id')->get()->map(fn (KelompokPajak $kelompok): array => [
            'Nama' => $kelompok->Nama,
            'Pajak' => array_values($kelompok->Detail->map(fn (KelompokPajakDetail $detail): array => [
                'KodeJenisPajak' => $detail->JenisPajak->Kode,
                'NamaJenisPajak' => $detail->JenisPajak->Nama,
                'DasarPengenaan' => $detail->DasarPengenaan->value,
                'LabelDasarPengenaan' => $detail->DasarPengenaan->AmbilLabel(),
            ])->all()),
        ])->all());
    }

    /**
     * Opsi kelompok pajak untuk form produk (F-03), urut nama. `LabelKategori` untuk kategori kosong (data lama) =
     * "Belum dikategorikan".
     *
     * @return list<array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>
     */
    public function AmbilOpsi(): array
    {
        return array_values(KelompokPajak::query()->orderBy('Nama')->orderBy('Id')->get()->map(fn (KelompokPajak $kelompok): array => [
            'Id' => $kelompok->Id,
            'Uuid' => $kelompok->Uuid,
            'Nama' => $kelompok->Nama,
            'Kategori' => $kelompok->Kategori?->value,
            'LabelKategori' => $kelompok->Kategori?->AmbilLabel() ?? 'Belum dikategorikan',
        ])->all());
    }

    /**
     * Halaman `Kelola/KelompokPajak/Daftar` (E.8): opsi + detail pajak + jumlah produk (dari domain Katalog, dikirim
     * pemanggil sebagai `[IdKelompokPajak => jumlah]`), jenis pajak platform, dan pilihan kategori & dasar pengenaan.
     *
     * @param  array<int, int>  $jumlahProduk
     * @return array{KelompokPajak: list<array<string, mixed>>, JenisPajak: list<array{Kode: string, Nama: string, Cakupan: string}>, Kategori: list<array{Nilai: string, Label: string}>, DasarPengenaan: list<array{Nilai: string, Label: string}>}
     */
    public function AmbilUntukHalaman(array $jumlahProduk = []): array
    {
        $kelompok = KelompokPajak::query()->with('Detail.JenisPajak')->orderBy('Nama')->orderBy('Id')->get();

        return [
            'KelompokPajak' => array_values($kelompok->map(fn (KelompokPajak $k): array => [
                'Uuid' => $k->Uuid,
                'Nama' => $k->Nama,
                'Kategori' => $k->Kategori?->value,
                'LabelKategori' => $k->Kategori?->AmbilLabel() ?? 'Belum dikategorikan',
                'Pajak' => array_values($k->Detail->map(fn (KelompokPajakDetail $detail): array => [
                    'KodeJenisPajak' => $detail->JenisPajak->Kode,
                    'NamaJenisPajak' => $detail->JenisPajak->Nama,
                    'DasarPengenaan' => $detail->DasarPengenaan->value,
                    'LabelDasarPengenaan' => $detail->DasarPengenaan->AmbilLabel(),
                ])->all()),
                'JumlahProduk' => $jumlahProduk[$k->Id] ?? 0,
            ])->all()),
            ...$this->AmbilOpsiFormulir(),
        ];
    }

    /**
     * Opsi formulir kelompok pajak (halaman daftar & halaman penuh `Kelola/KelompokPajak/Buat`): jenis pajak
     * platform serta pilihan kategori & dasar pengenaan. Tanpa angka tarif (CLAUDE.md #12).
     *
     * @return array{JenisPajak: list<array{Kode: string, Nama: string, Cakupan: string}>, Kategori: list<array{Nilai: string, Label: string}>, DasarPengenaan: list<array{Nilai: string, Label: string}>}
     */
    public function AmbilOpsiFormulir(): array
    {
        return [
            'JenisPajak' => array_values(JenisPajak::query()->orderBy('Id')->get()
                ->map(fn (JenisPajak $jenis): array => ['Kode' => $jenis->Kode, 'Nama' => $jenis->Nama, 'Cakupan' => $jenis->Cakupan->value])->all()),
            'Kategori' => array_map(fn (KategoriPajakProduk $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KategoriPajakProduk::cases()),
            'DasarPengenaan' => array_map(fn (DasarPengenaanPajak $d): array => ['Nilai' => $d->value, 'Label' => $d->AmbilLabel()], DasarPengenaanPajak::cases()),
        ];
    }

    /**
     * Bagian `KelompokPajak` katalog POS (F-03 D.3). Delta = `DiubahPada ≥ sejak` (perubahan detail memperbarui
     * `DiubahPada` kelompoknya). Kelompok pajak tidak pernah dihapus. `Pajak[].Kategori` (PRD v1.46, kunci tambahan)
     * = kategori jenis pajak `Ppn`/`Pbjt`/`Lainnya` untuk syarat profil pajak outlet di aplikasi.
     *
     * @return list<array{Uuid: string, Nama: string, Kategori: string|null, Pajak: list<array{KodeJenisPajak: string, Kategori: string, DasarPengenaan: string, Urutan: int}>}>
     */
    public function AmbilUntukPos(?CarbonImmutable $sejak): array
    {
        return array_values(KelompokPajak::query()
            ->with('Detail.JenisPajak')
            ->when($sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $sejak))
            ->orderBy('Id')
            ->get()
            ->map(fn (KelompokPajak $k): array => [
                'Uuid' => $k->Uuid,
                'Nama' => $k->Nama,
                'Kategori' => $k->Kategori?->value,
                'Pajak' => array_values($k->Detail->map(fn (KelompokPajakDetail $detail): array => [
                    'KodeJenisPajak' => $detail->JenisPajak->Kode,
                    'Kategori' => $detail->JenisPajak->Kategori->value,
                    'DasarPengenaan' => $detail->DasarPengenaan->value,
                    'Urutan' => $detail->Urutan,
                ])->all()),
            ])->all());
    }

    /** Id kelompok pajak tenant dari Uuid publiknya, atau null. */
    public function CariIdBerdasarkanUuid(string $uuid): ?int
    {
        $id = KelompokPajak::query()->where('Uuid', $uuid)->value('Id');

        return is_int($id) ? $id : null;
    }

    /** Id kelompok pajak tenant ber-kategori tertentu dengan Id terkecil (impor F-03), atau null. */
    public function CariIdBerdasarkanKategori(KategoriPajakProduk $kategori): ?int
    {
        $id = KelompokPajak::query()->where('Kategori', $kategori->value)->orderBy('Id')->value('Id');

        return is_int($id) ? $id : null;
    }

    /** Id kelompok pajak tenant dengan nama tertentu (tanpa beda huruf besar/kecil). */
    public function CariIdBerdasarkanNama(string $nama): ?int
    {
        $kelompok = KelompokPajak::query()->whereRaw('LOWER(Nama) = ?', [mb_strtolower(trim($nama))])->first();

        return $kelompok?->Id;
    }

    /**
     * Jenis pajak platform per kode (P-02).
     *
     * @param  list<string>  $kode
     * @return array<string, array{Kode: string, Nama: string, Cakupan: string, Kategori: string}>
     */
    public function AmbilJenisPajak(array $kode): array
    {
        if ($kode === []) {
            return [];
        }

        $hasil = [];

        foreach (JenisPajak::query()->whereIn('Kode', array_values(array_unique($kode)))->get() as $jenis) {
            $hasil[$jenis->Kode] = ['Kode' => $jenis->Kode, 'Nama' => $jenis->Nama, 'Cakupan' => $jenis->Cakupan->value, 'Kategori' => $jenis->Kategori->value];
        }

        return $hasil;
    }

    /**
     * Kategori jenis pajak per kode (PRD v1.46): pemetaan akun jurnal penjualan/retur. Kode tidak dikenal tidak ikut
     * (pemanggil memperlakukannya sebagai `Lainnya`).
     *
     * @param  list<string>  $kode
     * @return array<string, KategoriJenisPajak>
     */
    public function AmbilKategoriJenisPajak(array $kode): array
    {
        if ($kode === []) {
            return [];
        }

        $hasil = [];

        foreach (JenisPajak::query()->whereIn('Kode', array_values(array_unique($kode)))->get(['Kode', 'Kategori']) as $jenis) {
            $hasil[$jenis->Kode] = $jenis->Kategori;
        }

        return $hasil;
    }

    /**
     * Jenis pajak per kelompok pajak tenant aktif, urut detail (F-07b, PRD v1.46): dasar pencocokan himpunan pajak
     * per baris penjualan POS. Kelompok tenant lain tidak ikut. `Nama` & `DasarPengenaan` (PRD v2.06, estimasi total
     * pesan sendiri) = nama jenis pajak dan dasar pengenaan detail kelompok.
     *
     * @param  list<int>  $idKelompok
     * @return array<int, list<array{Kode: string, Kategori: KategoriJenisPajak, Nama: string, DasarPengenaan: DasarPengenaanPajak}>>
     */
    public function AmbilJenisPajakPerKelompok(array $idKelompok): array
    {
        if ($idKelompok === []) {
            return [];
        }

        $hasil = [];
        $detail = KelompokPajakDetail::query()
            ->with('JenisPajak')
            ->whereIn('IdKelompokPajak', array_values(array_unique($idKelompok)))
            ->orderBy('Urutan')
            ->orderBy('Id')
            ->get();

        foreach ($detail as $d) {
            $hasil[$d->IdKelompokPajak][] = ['Kode' => $d->JenisPajak->Kode, 'Kategori' => $d->JenisPajak->Kategori, 'Nama' => $d->JenisPajak->Nama, 'DasarPengenaan' => $d->DasarPengenaan];
        }

        return $hasil;
    }
}
