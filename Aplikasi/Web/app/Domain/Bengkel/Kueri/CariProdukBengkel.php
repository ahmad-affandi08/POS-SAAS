<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Kueri;

use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\CariProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukJual;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Persediaan\Kueri\StokTersediaGudang;

/**
 * Pencarian produk formulir perintah kerja (§9.10): jasa = produk berjenis Jasa; sparepart = produk berstok biasa
 * (tanpa batch/seri) beserta stok tersedia di lokasi Toko outlet. Satuan jual (ProdukSatuan) ikut, satuan jual bawaan
 * lebih dulu.
 *
 * **Tanpa harga**, sama seperti formulir grosir: harga baris ditentukan server (price engine + tier pelanggan) saat
 * perintah kerja disimpan, lalu tampil di halaman detail sebelum persetujuan diminta.
 */
final class CariProdukBengkel
{
    public function __construct(
        private readonly CariProduk $cari,
        private readonly InfoProdukStok $info,
        private readonly SatuanProdukJual $satuan,
        private readonly OutletPenjualan $outlet,
        private readonly StokTersediaGudang $stok,
    ) {}

    /**
     * @return list<array{Uuid: string, Nama: string, Sku: string|null, Jenis: string, Pelacakan: string, StokTersedia: string|null, Satuan: list<array{Uuid: string, Simbol: string, Konversi: string}>}>
     */
    public function Cari(string $kata, JenisBarisPerintahKerja $jenis, ?int $idOutlet, int $batas = 20): array
    {
        $hasil = $this->cari->Cari($kata, [$jenis === JenisBarisPerintahKerja::Jasa ? JenisProduk::Jasa : JenisProduk::Stok], $batas);
        $info = $this->info->AmbilDariUuid(array_map(fn (array $p): string => $p['Uuid'], $hasil));

        $id = array_values(array_map(fn (array $p): int => $info[$p['Uuid']]->id, array_filter($hasil, fn (array $p): bool => isset($info[$p['Uuid']]))));
        $satuan = $this->satuan->AmbilUntukProduk($id);
        $idGudang = $jenis === JenisBarisPerintahKerja::Sparepart && $idOutlet !== null ? $this->outlet->AmbilIdGudangToko($idOutlet) : null;
        $stok = $idGudang === null ? [] : $this->stok->AmbilProduk($idGudang, $id);

        return array_values(array_map(function (array $p) use ($info, $satuan, $stok, $jenis, $idGudang): array {
            $idProduk = $info[$p['Uuid']]->id ?? 0;

            return [
                'Uuid' => $p['Uuid'],
                'Nama' => $p['Nama'],
                'Sku' => $p['Sku'],
                'Jenis' => $jenis->value,
                'Pelacakan' => ($info[$p['Uuid']] ?? null)?->pelacakan->value ?? PelacakanProduk::Tidak->value,
                'StokTersedia' => $jenis === JenisBarisPerintahKerja::Sparepart && $idGudang !== null ? ($stok[$idProduk] ?? '0.0000') : null,
                'Satuan' => array_map(fn (array $s): array => ['Uuid' => $s['Uuid'], 'Simbol' => $s['Simbol'], 'Konversi' => $s['Konversi']], $satuan[$idProduk] ?? []),
            ];
        }, $hasil));
    }
}
