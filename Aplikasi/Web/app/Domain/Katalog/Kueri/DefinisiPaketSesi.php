<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\PaketSesi;
use App\Domain\Katalog\Model\PaketSesiProduk;
use App\Domain\Katalog\Model\Produk;

/**
 * Kueri publik definisi paket sesi (F-16d bagian 2) untuk domain Penjualan & Pelanggan: produk mana yang dijual sebagai
 * paket sesi, berapa sesinya, masa berlakunya, dan produk jasa yang boleh ditukar.
 */
final class DefinisiPaketSesi
{
    /**
     * Paket sesi per Id produk yang dijual (hanya yang ada definisinya; aktif atau tidak).
     *
     * @param  list<int>  $idProduk
     * @return array<int, array{IdPaketSesi: int, JumlahSesi: int, MasaBerlakuHari: int|null, Aktif: bool}>
     */
    public function AmbilPerProduk(array $idProduk): array
    {
        if ($idProduk === []) {
            return [];
        }

        $hasil = [];

        foreach (PaketSesi::query()->whereIn('IdProduk', array_values(array_unique($idProduk)))->get() as $p) {
            $hasil[$p->IdProduk] = [
                'IdPaketSesi' => $p->Id,
                'JumlahSesi' => $p->JumlahSesi,
                'MasaBerlakuHari' => $p->MasaBerlakuHari,
                'Aktif' => $p->Aktif,
            ];
        }

        return $hasil;
    }

    /** Produk boleh ditukar dengan sesi paket ini: ada di daftar, atau semua produk Jasa bila `SemuaProdukJasa`. */
    public function CekProdukBerlaku(int $idPaketSesi, int $idProduk): bool
    {
        $paket = PaketSesi::query()->find($idPaketSesi);

        if ($paket === null) {
            return false;
        }

        if (PaketSesiProduk::query()->where('IdPaketSesi', $idPaketSesi)->where('IdProduk', $idProduk)->exists()) {
            return true;
        }

        return $paket->SemuaProdukJasa
            && Produk::query()->whereKey($idProduk)->where('Jenis', JenisProduk::Jasa->value)->exists();
    }

    /**
     * Uuid & nama produk yang boleh ditukar per paket (untuk POS & tampilan). `Semua` = semua produk jasa.
     *
     * @param  list<int>  $idPaketSesi
     * @return array<int, array{Semua: bool, Produk: list<array{Uuid: string, Nama: string}>}>
     */
    public function AmbilProdukBerlaku(array $idPaketSesi): array
    {
        if ($idPaketSesi === []) {
            return [];
        }

        $hasil = [];

        foreach (PaketSesi::query()->whereIn('Id', array_values(array_unique($idPaketSesi)))->with('ProdukBerlaku.Produk:Id,Uuid,Nama')->get() as $p) {
            $hasil[$p->Id] = [
                'Semua' => $p->SemuaProdukJasa,
                'Produk' => array_values($p->ProdukBerlaku->map(fn (PaketSesiProduk $pp): array => [
                    'Uuid' => $pp->Produk->Uuid,
                    'Nama' => $pp->Produk->Nama,
                ])->all()),
            ];
        }

        return $hasil;
    }

    /** Id produk dari Uuid (dalam tenant aktif); null bila tidak ada. */
    public function CariIdProduk(string $uuid): ?int
    {
        $id = Produk::query()->where('Uuid', $uuid)->value('Id');

        return $id === null ? null : (int) $id;
    }

    public function AmbilNamaProduk(int $idProduk): ?string
    {
        $nama = Produk::query()->whereKey($idProduk)->value('Nama');

        return is_string($nama) ? $nama : null;
    }
}
