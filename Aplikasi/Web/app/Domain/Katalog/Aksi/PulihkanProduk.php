<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BR-03.2: pulihkan produk diarsipkan. Produk arsip tidak dihitung `BatasSku` (H9), jadi batas paket diperiksa ulang
 * untuk semua produk terhitung yang dipulihkan sekaligus (induk varian: anak-anaknya yang diarsipkan).
 * Idempoten: produk aktif dikembalikan apa adanya.
 */
final class PulihkanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk): Produk
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $produk): Produk {
            $this->penguncian->Kunci($idTenant);
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();

            if ($produk->DiarsipkanPada === null) {
                return $produk;
            }

            $anak = $produk->Jenis === JenisProduk::IndukVarian
                ? Produk::query()->where('IdInduk', $produk->Id)->whereNotNull('DiarsipkanPada')->lockForUpdate()->get()
                : new Collection;
            $jumlahTerhitung = ($produk->Jenis->CekDihitungBatasSku() ? 1 : 0)
                + $anak->filter(fn (Produk $satu): bool => $satu->Jenis->CekDihitungBatasSku())->count();

            if ($jumlahTerhitung > 0) {
                $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => $this->pemakaianSku->Hitung(), $jumlahTerhitung);
            }

            $produk->fill(['Aktif' => true, 'DiarsipkanPada' => null])->save();

            if ($anak->isNotEmpty()) {
                Produk::query()->whereKey($anak->modelKeys())->update(['Aktif' => true, 'DiarsipkanPada' => null]);
            }

            $this->audit->Catat('produk.pulihkan', $produk, ['Status' => 'Diarsipkan'], ['Status' => 'Aktif', 'JumlahVarian' => $anak->count()]);

            return $produk;
        });
    }
}
