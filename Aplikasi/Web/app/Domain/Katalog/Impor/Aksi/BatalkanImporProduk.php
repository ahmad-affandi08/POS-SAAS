<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor (BR-03.6): batalkan impor sebelum diterapkan (dari MenungguPemetaan atau Pratinjau). Tidak ada produk
 * yang berubah; berkas & laporan tetap ada sampai masa simpan habis. Audit `produk.impor.batalkan`.
 */
final class BatalkanImporProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(ImporProduk $impor): ImporProduk
    {
        return DB::transaction(function () use ($impor): ImporProduk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if (! $impor->Status->BisaBerubahKe(StatusImporProduk::Dibatalkan)) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Impor berstatus {$impor->Status->AmbilLabel()} tidak bisa dibatalkan.", 'Impor');
            }

            $statusLama = $impor->Status;
            $impor->UbahStatus(StatusImporProduk::Dibatalkan);
            $impor->save();
            $this->audit->Catat('produk.impor.batalkan', $impor, ['Status' => $statusLama->value], ['Status' => StatusImporProduk::Dibatalkan->value]);

            return $impor;
        });
    }
}
