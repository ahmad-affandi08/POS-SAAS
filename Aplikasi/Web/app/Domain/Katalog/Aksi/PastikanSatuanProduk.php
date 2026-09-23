<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor: satuan alternatif produk (misal dus isi 12). Idempoten: satuan yang sudah ada dengan konversi sama
 * dikembalikan; konversi berbeda = `KonversiTidakValid` (ubah lewat form produk). Konversi > 0; satuan dasar = 1.
 */
final class PastikanSatuanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $produk, Satuan $satuan, Kuantitas $konversi): ProdukSatuan
    {
        return DB::transaction(function () use ($produk, $satuan, $konversi): ProdukSatuan {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();
            $wajib = $satuan->Id === $produk->IdSatuanDasar ? Kuantitas::Dari(1) : $konversi;

            if ($konversi->Bandingkan(Kuantitas::Nol()) <= 0 || ! $konversi->SamaDengan($wajib)) {
                throw new PelanggaranAturanBisnis('KonversiTidakValid', "Isi satuan {$satuan->Nama} harus lebih dari 0 (satuan dasar selalu 1).", 'KonversiKeDasar');
            }

            $ada = ProdukSatuan::query()->where('IdProduk', $produk->Id)->where('IdSatuan', $satuan->Id)->first();

            if ($ada !== null) {
                if (! Kuantitas::Dari($ada->KonversiKeDasar)->SamaDengan($konversi)) {
                    throw new PelanggaranAturanBisnis('KonversiTidakValid', "Satuan {$satuan->Nama} sudah ada dengan isi {$ada->KonversiKeDasar}. Ubah lewat form produk.", 'KonversiKeDasar');
                }

                return $ada;
            }

            $baris = ProdukSatuan::query()->create([
                'IdProduk' => $produk->Id,
                'IdSatuan' => $satuan->Id,
                'KonversiKeDasar' => $konversi->KeString(),
                'DefaultJual' => false,
                'DefaultBeli' => false,
            ]);
            $this->audit->Catat('produk.satuan.tambah', $produk, null, ['IdSatuan' => $satuan->Id, 'KonversiKeDasar' => $konversi->KeString()]);

            return $baris;
        });
    }
}
