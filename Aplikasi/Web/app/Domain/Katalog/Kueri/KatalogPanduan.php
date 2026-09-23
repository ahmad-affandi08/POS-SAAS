<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\Satuan;

/**
 * Data katalog tenant aktif untuk panduan awal (F-01 langkah 4): kategori, satuan, produk terbaru, dan nama produk
 * yang sudah ada.
 */
final class KatalogPanduan
{
    /**
     * @return list<array{Uuid: string, Nama: string}>
     */
    public function AmbilKategori(): array
    {
        return array_values(Kategori::query()->orderBy('Urutan')->orderBy('Nama')->get()
            ->map(fn (Kategori $kategori): array => ['Uuid' => $kategori->Uuid, 'Nama' => $kategori->Nama])
            ->all());
    }

    public function CariIdKategoriUuid(string $uuid): ?int
    {
        return Kategori::query()->where('Uuid', $uuid)->first()?->Id;
    }

    /**
     * Id kategori per nama kecil (kategori akar).
     *
     * @return array<string, int>
     */
    public function AmbilIdKategoriPerNama(): array
    {
        $hasil = [];

        foreach (Kategori::query()->whereNull('IdInduk')->get(['Id', 'Nama']) as $kategori) {
            $hasil[mb_strtolower(trim($kategori->Nama))] ??= $kategori->Id;
        }

        return $hasil;
    }

    public function CariIdSatuan(string $kodeStandar): ?int
    {
        return Satuan::query()->where('KodeStandar', $kodeStandar)->first()?->Id;
    }

    /**
     * Nama produk yang sudah ada, huruf kecil (untuk menandai contoh yang sudah ditambahkan).
     *
     * @return list<string>
     */
    public function AmbilNamaProdukAda(): array
    {
        return array_values(array_map(fn (mixed $nama): string => mb_strtolower(trim((string) $nama)), Produk::query()->pluck('Nama')->all()));
    }

    /**
     * @return list<array{Uuid: string, Nama: string, NamaKategori: string|null, Harga: string}>
     */
    public function AmbilProdukTerbaru(int $jumlah = 50): array
    {
        $produk = Produk::query()->with('Kategori')->orderByDesc('Id')->limit($jumlah)->get();
        $harga = ProdukHarga::query()
            ->whereIn('IdProduk', $produk->pluck('Id')->all())
            ->whereNull('IdDaftarHarga')
            ->orderBy('JumlahMinimum')
            ->orderBy('Id')
            ->get()
            ->groupBy('IdProduk');

        return array_values($produk->map(fn (Produk $baris): array => [
            'Uuid' => $baris->Uuid,
            'Nama' => $baris->Nama,
            'NamaKategori' => $baris->Kategori?->Nama,
            'Harga' => $harga->get($baris->Id)?->first()->Harga ?? '0.00',
        ])->all());
    }
}
