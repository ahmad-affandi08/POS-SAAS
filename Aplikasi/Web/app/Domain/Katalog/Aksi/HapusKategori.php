<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03: hapus kategori yang tidak punya sub-kategori dan tidak dipakai produk yang belum dihapus
 * (`KategoriMasihDipakai`). Produk terhapus yang masih menunjuk kategori ini dilepas dulu. Jejak `Kategori` dicatat
 * untuk katalog POS.
 */
final class HapusKategori
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $pencatatHapus,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Kategori $kategori): void
    {
        DB::transaction(function () use ($kategori): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kategori = Kategori::query()->whereKey($kategori->Id)->lockForUpdate()->firstOrFail();

            if (Kategori::query()->where('IdInduk', $kategori->Id)->exists()) {
                throw new PelanggaranAturanBisnis('KategoriMasihDipakai', "Kategori {$kategori->Nama} masih punya sub-kategori. Pindahkan atau hapus sub-kategorinya dulu.");
            }

            $jumlahProduk = Produk::query()->where('IdKategori', $kategori->Id)->count();

            if ($jumlahProduk > 0) {
                throw new PelanggaranAturanBisnis('KategoriMasihDipakai', "Kategori {$kategori->Nama} masih dipakai {$jumlahProduk} produk. Pindahkan produknya ke kategori lain dulu.");
            }

            Produk::query()->onlyTrashed()->where('IdKategori', $kategori->Id)->update(['IdKategori' => null]);
            $lama = $kategori->only(['Nama', 'IdInduk', 'Urutan']);
            $kategori->delete();
            $this->pencatatHapus->Catat(EntitasKatalog::Kategori, $kategori->Uuid);
            $this->audit->Catat('kategori.hapus', $kategori, $lama);
        });
    }
}
