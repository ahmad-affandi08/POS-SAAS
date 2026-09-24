<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;

/**
 * Kategori tenant aktif dalam urutan pohon (induk lalu anak-anaknya, per `Urutan` lalu `Nama`) untuk pilihan form
 * dan halaman kategori (tipe FE `OpsiKategori`, F-03 E.1/E.5).
 */
final class PohonKategori
{
    /**
     * @return list<array{Uuid: string, Nama: string, Jalur: string, Kedalaman: int, UuidInduk: string|null}>
     */
    public function AmbilOpsi(): array
    {
        return array_map(fn (array $baris): array => [
            'Uuid' => $baris['Uuid'],
            'Nama' => $baris['Nama'],
            'Jalur' => $baris['Jalur'],
            'Kedalaman' => $baris['Kedalaman'],
            'UuidInduk' => $baris['UuidInduk'],
        ], $this->AmbilUrut());
    }

    /**
     * @return list<array{Uuid: string, Nama: string, Jalur: string, Kedalaman: int, UuidInduk: string|null, JumlahProduk: int, Urutan: int}>
     */
    public function AmbilUntukHalaman(): array
    {
        $jumlah = Produk::query()->whereNotNull('IdKategori')->whereNull('IdInduk')
            ->selectRaw('IdKategori, count(*) as Jumlah')->groupBy('IdKategori')->pluck('Jumlah', 'IdKategori');

        return array_map(fn (array $baris): array => [
            'Uuid' => $baris['Uuid'],
            'Nama' => $baris['Nama'],
            'Jalur' => $baris['Jalur'],
            'Kedalaman' => $baris['Kedalaman'],
            'UuidInduk' => $baris['UuidInduk'],
            'JumlahProduk' => (int) ($jumlah->get($baris['Id']) ?? 0),
            'Urutan' => $baris['Urutan'],
        ], $this->AmbilUrut());
    }

    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, Jalur: string, Kedalaman: int, UuidInduk: string|null, Urutan: int}>
     */
    private function AmbilUrut(): array
    {
        $semua = Kategori::query()->orderBy('Urutan')->orderBy('Nama')->get();
        $perInduk = $semua->groupBy(fn (Kategori $k): string => (string) $k->IdInduk);
        $uuid = $semua->pluck('Uuid', 'Id');
        $hasil = [];

        $susun = function (?int $idInduk, string $jalurInduk, int $kedalaman) use (&$susun, &$hasil, $perInduk, $uuid): void {
            foreach ($perInduk->get((string) $idInduk, collect()) as $kategori) {
                $jalur = $jalurInduk === '' ? $kategori->Nama : $jalurInduk.' › '.$kategori->Nama;
                $hasil[] = [
                    'Id' => $kategori->Id,
                    'Uuid' => $kategori->Uuid,
                    'Nama' => $kategori->Nama,
                    'Jalur' => $jalur,
                    'Kedalaman' => $kedalaman,
                    'UuidInduk' => $idInduk === null ? null : $uuid->get($idInduk),
                    'Urutan' => $kategori->Urutan,
                ];
                $susun($kategori->Id, $jalur, $kedalaman + 1);
            }
        };
        $susun(null, '', 1);

        return $hasil;
    }
}
