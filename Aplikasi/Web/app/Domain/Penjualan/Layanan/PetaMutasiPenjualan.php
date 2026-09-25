<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;

/**
 * Baris mutasi stok dokumen penjualan/void/retur untuk halaman back-office (F-07b, F-09): nama produk & lokasi stok,
 * jumlah bertanda (satuan dasar), perubahan nilai persediaan, dan tautan kartu stok pada tanggal bisnis mutasi.
 */
final class PetaMutasiPenjualan
{
    /**
     * @param  list<array{Id: int, KunciBaris: string, IdProduk: int, IdGudang: int, IdReferensiDetail: int|null, Jumlah: string, HppSatuan: string, TotalHpp: string, TanggalBisnis: string}>  $mutasi
     * @return list<array{Kunci: string, NamaProduk: string, NamaGudang: string, Jumlah: string, SimbolSatuan: string, TotalHpp: string, TautanKartuStok: string|null}>
     */
    public static function Petakan(array $mutasi, InfoProdukStok $infoProduk, InfoGudang $infoGudang): array
    {
        $produk = $infoProduk->AmbilBanyak(array_values(array_unique(array_column($mutasi, 'IdProduk'))), true);
        $gudang = $infoGudang->AmbilBanyak(array_values(array_unique(array_column($mutasi, 'IdGudang'))));

        return array_map(function (array $m) use ($produk, $gudang): array {
            $p = $produk[$m['IdProduk']] ?? null;
            $g = $gudang[$m['IdGudang']] ?? null;

            return [
                'Kunci' => (string) $m['Id'],
                'NamaProduk' => $p === null ? '' : $p->nama,
                'NamaGudang' => $g === null ? '' : $g->nama,
                'Jumlah' => $m['Jumlah'],
                'SimbolSatuan' => $p === null ? '' : $p->simbolSatuan,
                'TotalHpp' => $m['TotalHpp'],
                'TautanKartuStok' => $p === null || $g === null ? null : '/kelola/persediaan/kartu-stok?'.http_build_query([
                    'produk' => $p->uuid,
                    'gudang' => $g->uuid,
                    'dari' => $m['TanggalBisnis'],
                    'sampai' => $m['TanggalBisnis'],
                ]),
            ];
        }, $mutasi);
    }
}
