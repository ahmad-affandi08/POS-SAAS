<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Layanan\AturanProduk;
use App\Domain\Katalog\Layanan\PemetaGalatUnikKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor (BR-03.1): tambah satu barcode ke satuan produk. Idempoten bila barcode sudah milik produk yang sama
 * (dikembalikan apa adanya); milik produk lain = `BR-03.1`.
 */
final class TambahBarcodeProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(ProdukSatuan $satuan, string $barcode): ProdukBarcode
    {
        $barcode = AturanProduk::NormalisasiBarcode($barcode, 'Barcode');

        try {
            return DB::transaction(function () use ($satuan, $barcode): ProdukBarcode {
                $this->penguncian->Kunci($this->konteks->Wajib());
                $satuan = ProdukSatuan::query()->whereKey($satuan->Id)->lockForUpdate()->firstOrFail();
                $ada = ProdukBarcode::query()->where('Barcode', $barcode)->first();

                if ($ada !== null) {
                    if ($ada->IdProduk === $satuan->IdProduk) {
                        return $ada;
                    }

                    $nama = Produk::query()->withTrashed()->whereKey($ada->IdProduk)->value('Nama');

                    throw new PelanggaranAturanBisnis('BR-03.1', "Barcode {$barcode} sudah dipakai produk {$nama}.", 'Barcode');
                }

                $baris = ProdukBarcode::query()->create(['IdProduk' => $satuan->IdProduk, 'IdProdukSatuan' => $satuan->Id, 'Barcode' => $barcode]);
                $this->audit->Catat('produk.barcode.tambah', $baris, null, ['IdProduk' => $satuan->IdProduk, 'Barcode' => $barcode]);

                return $baris;
            });
        } catch (UniqueConstraintViolationException $galat) {
            throw PemetaGalatUnikKatalog::Petakan($galat);
        }
    }
}
