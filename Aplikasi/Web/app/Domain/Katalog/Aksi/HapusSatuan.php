<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * F-03: hapus satuan tenant yang tidak dipakai produk (termasuk produk terhapus) maupun satuan produk
 * (`SatuanMasihDipakai`). Pelanggaran FK tabel lain (resep, dll.) menjadi pengaman terakhir. Jejak `Satuan` dicatat.
 */
final class HapusSatuan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $pencatatHapus,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Satuan $satuan): void
    {
        try {
            DB::transaction(function () use ($satuan): void {
                $this->penguncian->Kunci($this->konteks->Wajib());
                $satuan = Satuan::query()->whereKey($satuan->Id)->lockForUpdate()->firstOrFail();

                if (Produk::query()->withTrashed()->where('IdSatuanDasar', $satuan->Id)->exists()
                    || ProdukSatuan::query()->where('IdSatuan', $satuan->Id)->exists()) {
                    throw self::GalatDipakai($satuan);
                }

                $lama = $satuan->only(['KodeStandar', 'Nama', 'Simbol', 'BolehDesimal']);
                $satuan->delete();
                $this->pencatatHapus->Catat(EntitasKatalog::Satuan, $satuan->Uuid);
                $this->audit->Catat('satuan.hapus', $satuan, $lama);
            });
        } catch (QueryException $galat) {
            // 1451: baris masih dirujuk FK tabel lain.
            if ((int) ($galat->errorInfo[1] ?? 0) === 1451) {
                throw self::GalatDipakai($satuan);
            }

            throw $galat;
        }
    }

    private static function GalatDipakai(Satuan $satuan): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('SatuanMasihDipakai', "Satuan {$satuan->Nama} masih dipakai produk, jadi tidak bisa dihapus.");
    }
}
