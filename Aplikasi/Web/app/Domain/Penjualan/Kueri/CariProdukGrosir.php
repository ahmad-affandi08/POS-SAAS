<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukJual;
use App\Domain\Persediaan\Kueri\CariProdukStok;

/**
 * Pencarian produk untuk formulir SO grosir (F-12, §9.7): produk berstok beserta saldo di lokasi, ditambah daftar
 * satuan jual & konversinya (satuan jual bawaan lebih dulu).
 *
 * **Tanpa harga, dan itu disengaja.** Harga SO grosir ditentukan server lewat price engine saat draf disimpan
 * (daftar harga bertingkat per jumlah + tier pelanggan), lalu di-snapshot di barisnya. Kalau pencarian ini juga
 * menghitung harga, akan ada dua tempat yang menjawab "berapa harganya" dan keduanya bisa berbeda — yang dilihat
 * operator di form versus yang tersimpan di dokumen. Operator melihat harga & totalnya di halaman draf setelah
 * disimpan, sebelum mengonfirmasi.
 */
final class CariProdukGrosir
{
    public function __construct(
        private readonly CariProdukStok $cari,
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukJual $satuan,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Cari(string $kata, ?int $idGudang, int $batas = 20): array
    {
        $hasil = $this->cari->Cari($kata, $idGudang, $batas);
        $info = $this->infoProduk->AmbilDariUuid(array_map(fn (array $p): string => (string) $p['Uuid'], $hasil));
        $satuan = $this->satuan->AmbilUntukProduk(array_values(array_map(fn ($i): int => $i->id, $info)));

        return array_map(function (array $p) use ($info, $satuan): array {
            $id = $info[$p['Uuid']]->id ?? 0;

            return [...$p, 'Satuan' => array_map(fn (array $s): array => [
                'Uuid' => $s['Uuid'],
                'Simbol' => $s['Simbol'],
                'Nama' => $s['Nama'],
                'Konversi' => $s['Konversi'],
                'DefaultJual' => $s['DefaultJual'],
            ], $satuan[$id] ?? [])];
        }, $hasil);
    }
}
