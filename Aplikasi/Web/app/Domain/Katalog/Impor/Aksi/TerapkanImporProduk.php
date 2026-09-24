<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PemeriksaBatasSkuImpor;
use App\Domain\Katalog\Impor\Layanan\PengirimTugasImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor langkah 4 (BR-03.6): mulai menerapkan baris valid. Syarat: status Pratinjau, ada baris valid, dan
 * produk baru masih muat di `BatasSku` paket (BR-02.1, dihitung ulang sekarang). Status → Menerapkan, lalu
 * `TerapkanImporProdukTugas` dijalankan langsung (≤ `katalog.Impor.BatasBarisSinkron` baris) atau lewat antrean.
 * Audit `produk.impor.terapkan` (mulai).
 */
final class TerapkanImporProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PemeriksaBatasSkuImpor $batasSku,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(ImporProduk $impor): ImporProduk
    {
        $impor = DB::transaction(function () use ($impor): ImporProduk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporProduk::Pratinjau) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Impor berstatus {$impor->Status->AmbilLabel()} tidak bisa diterapkan.", 'Impor');
            }

            if ($impor->JumlahValid === 0) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', 'Tidak ada baris valid untuk diimpor. Perbaiki berkas lalu unggah ulang.', 'Impor');
            }

            $blokir = $this->batasSku->Periksa($impor);

            if ($blokir !== null) {
                throw new PelanggaranAturanBisnis('BR-02.1', $blokir, 'Impor');
            }

            $impor->UbahStatus(StatusImporProduk::Menerapkan);
            $impor->DiterapkanMulaiPada = now();
            $impor->save();
            $this->audit->Catat('produk.impor.terapkan', $impor, null, ['Tahap' => 'Mulai', 'JumlahValid' => $impor->JumlahValid]);

            return $impor;
        });

        PengirimTugasImpor::KirimPenerapan($impor);

        return $impor->refresh();
    }
}
