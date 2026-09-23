<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * BR-03.2: arsipkan produk (tidak tampil di POS & daftar aktif, riwayat tetap utuh, SKU tetap dimiliki). Induk varian
 * ikut mengarsipkan semua anaknya. Idempoten: produk yang sudah diarsipkan dikembalikan apa adanya.
 * Invarian: `Aktif == (DiarsipkanPada IS NULL)`.
 */
final class ArsipkanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk): Produk
    {
        return DB::transaction(function () use ($produk): Produk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();

            if ($produk->DiarsipkanPada !== null) {
                return $produk;
            }

            $waktu = now();
            $produk->fill(['Aktif' => false, 'DiarsipkanPada' => $waktu])->save();
            $jumlahAnak = 0;

            if ($produk->Jenis === JenisProduk::IndukVarian) {
                $jumlahAnak = Produk::query()->where('IdInduk', $produk->Id)->whereNull('DiarsipkanPada')
                    ->update(['Aktif' => false, 'DiarsipkanPada' => $waktu]);
            }

            $this->audit->Catat('produk.arsipkan', $produk, ['Status' => 'Aktif'], ['Status' => 'Diarsipkan', 'JumlahVarian' => $jumlahAnak]);

            return $produk;
        });
    }
}
