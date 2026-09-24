<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-03: ganti gambar produk. Berkas baru ditulis dulu (nama berversi); gambar lama dihapus setelah commit, dan
 * berkas baru dihapus lagi bila transaksi gagal. Audit `produk.gambar.ubah`.
 */
final class UnggahGambarProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenyimpanGambarProduk $penyimpan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk, UploadedFile $berkas): Produk
    {
        $pathBaru = $this->penyimpan->Simpan($produk, $berkas);

        try {
            return DB::transaction(function () use ($produk, $pathBaru): Produk {
                $this->penguncian->Kunci($this->konteks->Wajib());
                $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();
                $pathLama = $produk->PathGambar;
                $produk->fill(['PathGambar' => $pathBaru])->save();
                DB::afterCommit(fn () => $this->penyimpan->Hapus($pathLama));
                // Audit mencatat versi gambar, bukan path penyimpanan internal.
                $this->audit->Catat('produk.gambar.ubah', $produk, ['VersiGambar' => PenyimpanGambarProduk::AmbilVersiDariPath($pathLama)], ['VersiGambar' => PenyimpanGambarProduk::AmbilVersiDariPath($pathBaru)]);

                return $produk;
            });
        } catch (Throwable $galat) {
            $this->penyimpan->Hapus($pathBaru);

            throw $galat;
        }
    }
}
