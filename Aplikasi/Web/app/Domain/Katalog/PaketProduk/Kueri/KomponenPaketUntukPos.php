<?php

declare(strict_types=1);

namespace App\Domain\Katalog\PaketProduk\Kueri;

use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\PaketProduk\Model\PaketProdukDetail;

/**
 * Bagian katalog POS `PaketProdukDetail` (F-03 D.3). Tanpa `sejak`: komponen paket yang belum dihapus; dengan
 * `sejak`: baris yang berubah sejak itu. Baris yang dilepas dikirim lewat `Terhapus`.
 */
final class KomponenPaketUntukPos implements BagianKatalogPos
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function AmbilBagian(KonteksKatalogPos $konteks): array
    {
        $detail = PaketProdukDetail::query()
            ->where('IdTenant', $konteks->idTenant)
            ->when($konteks->sejak === null, fn ($kueri) => $kueri->whereIn('IdProdukPaket', Produk::query()->select('Id')))
            ->when($konteks->sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $konteks->sejak))
            ->orderBy('IdProdukPaket')->orderBy('Urutan')
            ->get();
        $idProduk = $detail->pluck('IdProdukPaket')->merge($detail->pluck('IdProdukKomponen'))->unique()->values()->all();
        $uuidProduk = Produk::query()->withTrashed()->whereKey($idProduk)->pluck('Uuid', 'Id');

        return ['PaketProdukDetail' => array_values($detail->map(fn (PaketProdukDetail $baris): array => [
            'Uuid' => $baris->Uuid,
            'UuidProdukPaket' => $uuidProduk->get($baris->IdProdukPaket),
            'UuidProdukKomponen' => $uuidProduk->get($baris->IdProdukKomponen),
            'Jumlah' => $baris->Jumlah,
            'AlokasiHarga' => $baris->AlokasiHarga,
        ])->all())];
    }
}
