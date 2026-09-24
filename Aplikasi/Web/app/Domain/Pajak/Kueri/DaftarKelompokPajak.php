<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Kueri;

use App\Domain\Pajak\Enum\DasarPengenaanPajak;
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
            'JenisPajak' => array_values(JenisPajak::query()->orderBy('Id')->get()
                ->map(fn (JenisPajak $jenis): array => ['Kode' => $jenis->Kode, 'Nama' => $jenis->Nama, 'Cakupan' => $jenis->Cakupan->value])->all()),
            'Kategori' => array_map(fn (KategoriPajakProduk $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KategoriPajakProduk::cases()),
            'DasarPengenaan' => array_map(fn (DasarPengenaanPajak $d): array => ['Nilai' => $d->value, 'Label' => $d->AmbilLabel()], DasarPengenaanPajak::cases()),
        ];
    }

    /**
     * Bagian `KelompokPajak` katalog POS (F-03 D.3). Delta = `DiubahPada ≥ sejak` (perubahan detail memperbarui
     * `DiubahPada` kelompoknya). Kelompok pajak tidak pernah dihapus.
     *
     * @return list<array{Uuid: string, Nama: string, Kategori: string|null, Pajak: list<array{KodeJenisPajak: string, DasarPengenaan: string, Urutan: int}>}>
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
     * @return array<string, array{Kode: string, Nama: string, Cakupan: string}>
     */
    public function AmbilJenisPajak(array $kode): array
    {
        if ($kode === []) {
            return [];
        }

        $hasil = [];

        foreach (JenisPajak::query()->whereIn('Kode', array_values(array_unique($kode)))->get() as $jenis) {
            $hasil[$jenis->Kode] = ['Kode' => $jenis->Kode, 'Nama' => $jenis->Nama, 'Cakupan' => $jenis->Cakupan->value];
        }

        return $hasil;
    }
}
