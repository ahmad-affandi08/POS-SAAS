<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Layanan\PembuatBarcode;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 (BR-03.1, H8): buat barcode internal EAN-13 berawalan `katalog.Barcode.Awalan` untuk satuan produk yang
 * belum punya barcode pabrik. Hanya atas permintaan; setiap panggilan menambah satu barcode baru.
 */
final class BuatBarcodeInternal
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PembuatBarcode $pembuat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(ProdukSatuan $satuan): ProdukBarcode
    {
        return DB::transaction(function () use ($satuan): ProdukBarcode {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $satuan = ProdukSatuan::query()->whereKey($satuan->Id)->lockForUpdate()->firstOrFail();
            $barcode = ProdukBarcode::query()->create([
                'IdProduk' => $satuan->IdProduk,
                'IdProdukSatuan' => $satuan->Id,
                'Barcode' => $this->pembuat->Buat(),
            ]);
            $this->audit->Catat('produk.barcode.buat', $barcode, null, ['IdProduk' => $satuan->IdProduk, 'Barcode' => $barcode->Barcode]);

            return $barcode;
        });
    }
}
