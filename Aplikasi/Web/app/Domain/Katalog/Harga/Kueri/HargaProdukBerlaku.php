<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Harga\Data\DataBarisProdukHarga;
use App\Domain\Katalog\Harga\Data\DataDaftarHargaResolusi;
use App\Domain\Katalog\Harga\Data\DataKatalogHarga;
use App\Domain\Katalog\Harga\Data\DataPermintaanHarga;
use App\Domain\Katalog\Harga\Data\HasilHarga;
use App\Domain\Katalog\Harga\Layanan\PenentuHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Harga satuan yang berlaku di server (lapis 3–5 price engine F-03) untuk F-07, self-order, dan toko online: memuat
 * daftar harga tenant & baris harga satuan itu, lalu memanggil `PenentuHarga` (algoritma sama dengan POS & test
 * vector). Null = satuan tanpa harga dasar (hanya untuk pembelian).
 */
final class HargaProdukBerlaku
{
    public function __construct(
        private readonly PenentuHarga $penentu,
        private readonly PetaUuidOutlet $petaOutlet,
    ) {}

    public function Tentukan(
        Produk $produk,
        ProdukSatuan $satuan,
        Kuantitas $jumlah,
        ?int $idOutlet,
        ?KanalPenjualan $kanal,
        ?string $tier,
        CarbonImmutable $waktu,
    ): ?HasilHarga {
        $baris = ProdukHarga::query()->where('IdProdukSatuan', $satuan->Id)->where('IdProduk', $produk->Id)->get();
        $daftar = DaftarHarga::query()->whereIn('Id', $baris->pluck('IdDaftarHarga')->filter()->unique()->values()->all())->get();
        $idOutletDaftar = [];

        foreach ($daftar as $item) {
            $idOutletDaftar = [...$idOutletDaftar, ...($item->IdOutlet ?? [])];
        }

        if ($idOutlet !== null) {
            $idOutletDaftar[] = $idOutlet;
        }

        $uuidOutlet = $this->petaOutlet->Ambil(array_values(array_unique($idOutletDaftar)));
        $uuidDaftar = $daftar->pluck('Uuid', 'Id');

        $katalog = new DataKatalogHarga(
            array_values($daftar->map(fn (DaftarHarga $item): DataDaftarHargaResolusi => self::KeResolusi($item, $uuidOutlet))->all()),
            array_values($baris->map(fn (ProdukHarga $item): DataBarisProdukHarga => new DataBarisProdukHarga(
                $produk->Uuid,
                $satuan->Uuid,
                $item->IdDaftarHarga === null ? null : (string) $uuidDaftar->get($item->IdDaftarHarga),
                Kuantitas::Dari($item->JumlahMinimum),
                Uang::Dari($item->Harga),
            ))->all()),
        );

        return $this->penentu->Tentukan($katalog, new DataPermintaanHarga(
            $produk->Uuid,
            $satuan->Uuid,
            $jumlah,
            $idOutlet === null ? null : ($uuidOutlet[$idOutlet] ?? null),
            $kanal,
            $tier,
            $waktu->utc(),
        ));
    }

    /**
     * @param  array<int, string>  $uuidOutlet
     */
    public static function KeResolusi(DaftarHarga $daftar, array $uuidOutlet): DataDaftarHargaResolusi
    {
        return new DataDaftarHargaResolusi(
            $daftar->Uuid,
            $daftar->Aktif,
            $daftar->IdOutlet === null ? null : array_values(array_filter(array_map(fn (int $id): ?string => $uuidOutlet[$id] ?? null, $daftar->IdOutlet))),
            $daftar->Kanal,
            $daftar->TierPelanggan,
            $daftar->MulaiPada === null ? null : CarbonImmutable::instance($daftar->MulaiPada)->utc(),
            $daftar->SelesaiPada === null ? null : CarbonImmutable::instance($daftar->SelesaiPada)->utc(),
            $daftar->Prioritas,
        );
    }
}
