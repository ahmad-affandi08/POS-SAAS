<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;

/**
 * Bagian katalog POS Tim 2 (F-03 D.3): `KelompokPajak`, `DaftarHarga`, `ProdukHarga`. Daftar harga dikirim **semua**
 * (dengan `UuidOutlet`) karena `PenentuHarga` di POS memeriksa outlet sendiri. Lengkap = semua baris; delta =
 * `DiubahPada ≥ sejak` (baris harga yang dihapus dikirim lewat `Terhapus`). Uang & jumlah string, waktu ISO UTC.
 */
final class HargaPajakUntukPos implements BagianKatalogPos
{
    public function __construct(
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly PetaUuidOutlet $petaOutlet,
    ) {}

    public function AmbilBagian(KonteksKatalogPos $konteks): array
    {
        $sejak = $konteks->sejak;
        $daftar = DaftarHarga::query()->when($sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $sejak))->orderBy('Id')->get();
        $harga = ProdukHarga::query()->when($sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $sejak))->orderBy('Id')->get();
        $idOutlet = [];

        foreach ($daftar as $item) {
            $idOutlet = [...$idOutlet, ...($item->IdOutlet ?? [])];
        }

        $uuidOutlet = $this->petaOutlet->Ambil(array_values(array_unique($idOutlet)));
        $uuidProduk = Produk::query()->withTrashed()->whereIn('Id', $harga->pluck('IdProduk')->unique()->all())->pluck('Uuid', 'Id');
        $uuidSatuan = ProdukSatuan::query()->whereIn('Id', $harga->pluck('IdProdukSatuan')->unique()->all())->pluck('Uuid', 'Id');
        $uuidDaftar = DaftarHarga::query()->whereIn('Id', $harga->pluck('IdDaftarHarga')->filter()->unique()->all())->pluck('Uuid', 'Id');

        return [
            'KelompokPajak' => $this->kelompokPajak->AmbilUntukPos($sejak),
            'DaftarHarga' => array_values($daftar->map(fn (DaftarHarga $d): array => [
                'Uuid' => $d->Uuid,
                'Nama' => $d->Nama,
                'UuidOutlet' => $d->IdOutlet === null ? null : array_values(array_filter(array_map(fn (int $id): ?string => $uuidOutlet[$id] ?? null, $d->IdOutlet))),
                'Kanal' => $d->Kanal?->value,
                'TierPelanggan' => $d->TierPelanggan,
                'MulaiPada' => $d->MulaiPada?->utc()->toIso8601ZuluString(),
                'SelesaiPada' => $d->SelesaiPada?->utc()->toIso8601ZuluString(),
                'Prioritas' => $d->Prioritas,
                'Aktif' => $d->Aktif,
            ])->all()),
            'ProdukHarga' => array_values($harga->map(fn (ProdukHarga $h): array => [
                'Uuid' => $h->Uuid,
                'UuidProduk' => $uuidProduk->get($h->IdProduk),
                'UuidProdukSatuan' => $uuidSatuan->get($h->IdProdukSatuan),
                'UuidDaftarHarga' => $h->IdDaftarHarga === null ? null : $uuidDaftar->get($h->IdDaftarHarga),
                'JumlahMinimum' => $h->JumlahMinimum,
                'Harga' => $h->Harga,
            ])->all()),
        ];
    }
}
