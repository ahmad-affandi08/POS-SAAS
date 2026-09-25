<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukPembelian;
use App\Domain\Persediaan\Kueri\CariProdukStok;

/**
 * Pencarian produk untuk formulir pembelian (F-04 fase 1; tipe FE `HasilCariProdukPembelian`): hasil
 * `CariProdukStok` (produk berstok, saldo & HPP di lokasi) ditambah daftar satuan pembelian beserta konversinya
 * (satuan beli bawaan lebih dulu). Satuan dasar selalu ada (konversi 1).
 */
final class CariProdukPembelian
{
    public function __construct(
        private readonly CariProdukStok $cari,
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukPembelian $satuan,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Cari(string $kata, ?int $idGudang, int $batas = 20): array
    {
        $hasil = $this->cari->Cari($kata, $idGudang, $batas);
        $info = $this->infoProduk->AmbilDariUuid(array_map(fn (array $p): string => $p['Uuid'], $hasil));
        $satuan = $this->satuan->AmbilUntukProduk(array_values(array_map(fn ($i): int => $i->id, $info)));

        return array_map(function (array $p) use ($info, $satuan): array {
            $id = $info[$p['Uuid']]->id ?? 0;

            return [...$p, 'Satuan' => array_map(fn (array $s): array => [
                'Uuid' => $s['Uuid'],
                'Simbol' => $s['Simbol'],
                'Nama' => $s['Nama'],
                'Konversi' => $s['Konversi'],
                'DefaultBeli' => $s['DefaultBeli'],
            ], $satuan[$id] ?? [])];
        }, $hasil);
    }
}
