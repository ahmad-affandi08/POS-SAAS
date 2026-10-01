<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHabis;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;

/**
 * F-17 BR-17.2: keadaan "habis" (86) satu produk di tiap outlet aktif yang boleh diakses pelaku, untuk panel di
 * halaman detail produk. Produk induk varian tidak punya keadaan sendiri (ditandai per varian), jadi hasilnya null.
 */
final class KetersediaanProdukPerOutlet
{
    public function __construct(private readonly PetaUuidOutlet $petaOutlet) {}

    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return list<array{UuidOutlet: string, NamaOutlet: string, Habis: bool}>|null
     */
    public function Ambil(Produk $produk, ?array $idOutletBoleh): ?array
    {
        if ($produk->Jenis === JenisProduk::IndukVarian) {
            return null;
        }

        $habis = array_flip(ProdukHabis::query()->where('IdProduk', $produk->Id)->pluck('IdOutlet')->all());

        return array_map(fn (array $outlet): array => [
            'UuidOutlet' => $outlet['Uuid'],
            'NamaOutlet' => $outlet['Nama'],
            'Habis' => isset($habis[$outlet['Id']]),
        ], $this->petaOutlet->AmbilRingkas($idOutletBoleh, true));
    }
}
