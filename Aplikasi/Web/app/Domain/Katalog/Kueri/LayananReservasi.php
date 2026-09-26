<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;

/**
 * F-07 mode service: layanan jasa yang bisa direservasi = produk `Jasa` aktif berdurasi (`DurasiMenit`), tidak
 * diarsipkan. Reservasi online hanya layanan `TampilOnline`. Harga = harga dasar (tanpa daftar harga, minimum 1).
 */
final class LayananReservasi
{
    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, DurasiMenit: int, Harga: string|null}>
     */
    public function Ambil(bool $hanyaOnline = false): array
    {
        $produk = Produk::query()
            ->where('Jenis', JenisProduk::Jasa->value)
            ->where('Aktif', true)
            ->whereNull('DiarsipkanPada')
            ->whereNotNull('DurasiMenit')
            ->when($hanyaOnline, fn ($k) => $k->where('TampilOnline', true))
            ->orderBy('Nama')
            ->get(['Id', 'Uuid', 'Nama', 'DurasiMenit']);
        $harga = ProdukHarga::query()
            ->whereIn('IdProduk', $produk->pluck('Id')->all())
            ->whereNull('IdDaftarHarga')
            ->where('JumlahMinimum', '<=', 1)
            ->orderBy('Harga')
            ->get(['IdProduk', 'Harga'])
            ->unique('IdProduk')
            ->keyBy('IdProduk');

        return array_values($produk->map(fn (Produk $p): array => [
            'Id' => $p->Id,
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'DurasiMenit' => (int) $p->DurasiMenit,
            'Harga' => ($h = $harga->get($p->Id)) === null ? null : Uang::Dari($h->Harga)->KeString(),
        ])->all());
    }

    /**
     * @return array{Id: int, Uuid: string, Nama: string, DurasiMenit: int, Harga: string|null}|null
     */
    public function Cari(string $uuid, bool $hanyaOnline = false): ?array
    {
        foreach ($this->Ambil($hanyaOnline) as $layanan) {
            if ($layanan['Uuid'] === $uuid) {
                return $layanan;
            }
        }

        return null;
    }

    /**
     * Nama layanan per Id (untuk daftar reservasi), termasuk yang sudah diarsipkan.
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function AmbilNama(array $id): array
    {
        return array_map('strval', Produk::withTrashed()->whereIn('Id', $id)->pluck('Nama', 'Id')->all());
    }

    /**
     * Uuid produk per Id (untuk antrian kasir), termasuk yang sudah diarsipkan.
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function AmbilUuid(array $id): array
    {
        return array_map('strval', Produk::withTrashed()->whereIn('Id', $id)->pluck('Uuid', 'Id')->all());
    }
}
