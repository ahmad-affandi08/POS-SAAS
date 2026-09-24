<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Layanan\PenghubungHargaProduk;
use App\Domain\Katalog\Layanan\PenilaiPemakaianProduk;
use App\Domain\Katalog\Layanan\PenyelarasSatuanProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukGudang;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * BR-03.2: hapus produk yang belum pernah dipakai (semua `PemeriksaPemakaianProduk` mengembalikan null); produk yang
 * sudah dipakai hanya bisa diarsipkan.
 * - Induk varian: setiap anak juga harus belum dipakai; anak dihapus lebih dulu, lalu induknya diperiksa.
 * - Barcode dihapus (jejak), harga setiap satuan dihapus lewat Tim 2, batas stok dihapus.
 * - SKU dan kunci varian dilepas (null) lalu soft delete (`DihapusPada`); POS menerima `Dihapus: true`.
 */
final class HapusProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenilaiPemakaianProduk $penilai,
        private readonly PenyelarasSatuanProduk $penyelaras,
        private readonly PenghubungHargaProduk $harga,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk): void
    {
        DB::transaction(function () use ($produk): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();

            if ($produk->Jenis === JenisProduk::IndukVarian) {
                foreach (Produk::query()->where('IdInduk', $produk->Id)->lockForUpdate()->get() as $anak) {
                    $this->PastikanBelumDipakai($anak, "Varian {$anak->Nama} ");
                    $this->Hapus($anak);
                }
            }

            $this->PastikanBelumDipakai($produk, '');
            $this->Hapus($produk);
        });
    }

    private function PastikanBelumDipakai(Produk $produk, string $awalan): void
    {
        $alasan = $this->penilai->AmbilAlasan($produk);

        if ($alasan !== null) {
            throw new PelanggaranAturanBisnis('BR-03.2', ($awalan === '' ? 'Produk ini ' : $awalan).$alasan.'. Arsipkan produk ini.');
        }
    }

    private function Hapus(Produk $produk): void
    {
        $lama = ['Sku' => $produk->Sku, 'Nama' => $produk->Nama, 'Jenis' => $produk->Jenis->value];

        foreach (ProdukBarcode::query()->where('IdProduk', $produk->Id)->get() as $barcode) {
            $this->penyelaras->HapusBarcode($barcode);
        }

        foreach (ProdukSatuan::query()->where('IdProduk', $produk->Id)->get() as $satuan) {
            $this->harga->HapusHargaSatuan($satuan, 'Manual');
        }

        ProdukGudang::query()->where('IdProduk', $produk->Id)->delete();
        $produk->fill(['Sku' => null, 'KunciVarian' => null])->save();
        $produk->delete();

        $this->audit->Catat('produk.hapus', $produk, $lama);
    }
}
