<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03: hapus gambar produk. Berkas dihapus setelah commit. Idempoten: produk tanpa gambar dikembalikan apa adanya.
 */
final class HapusGambarProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenyimpanGambarProduk $penyimpan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk): Produk
    {
        return DB::transaction(function () use ($produk): Produk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();
            $pathLama = $produk->PathGambar;

            if ($pathLama === null) {
                return $produk;
            }

            $produk->fill(['PathGambar' => null])->save();
            DB::afterCommit(fn () => $this->penyimpan->Hapus($pathLama));
            $this->audit->Catat('produk.gambar.hapus', $produk, ['PathGambar' => $pathLama]);

            return $produk;
        });
    }
}
