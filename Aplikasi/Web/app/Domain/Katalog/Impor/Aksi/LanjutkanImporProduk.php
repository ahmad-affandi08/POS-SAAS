<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PengirimTugasImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor (BR-03.6): lanjutkan penerapan yang berhenti. Boleh bila impor Gagal saat menerapkan (misal batas SKU
 * habis atau galat sistem), atau masih Menerapkan tetapi tanpa kemajuan lebih dari 10 menit (worker terhenti).
 * Hanya baris yang belum diterapkan yang diproses (tepat sekali). Audit `produk.impor.lanjutkan`.
 */
final class LanjutkanImporProduk
{
    public const MENIT_TERHENTI = 10;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public static function CekBolehLanjutkan(ImporProduk $impor): bool
    {
        if ($impor->Status === StatusImporProduk::Gagal) {
            return $impor->DiterapkanMulaiPada !== null;
        }

        return $impor->Status === StatusImporProduk::Menerapkan
            && $impor->DiubahPada !== null
            && $impor->DiubahPada->lt(now()->subMinutes(self::MENIT_TERHENTI));
    }

    public function Jalankan(ImporProduk $impor): ImporProduk
    {
        $impor = DB::transaction(function () use ($impor): ImporProduk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if (! self::CekBolehLanjutkan($impor)) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', 'Impor ini tidak bisa dilanjutkan sekarang. Muat ulang halaman untuk melihat statusnya.', 'Impor');
            }

            $statusLama = $impor->Status;

            if ($impor->Status === StatusImporProduk::Gagal) {
                $impor->UbahStatus(StatusImporProduk::Menerapkan);
            }

            $impor->PesanGalat = null;
            $impor->touch();
            $impor->save();
            $this->audit->Catat('produk.impor.lanjutkan', $impor, ['Status' => $statusLama->value], ['Status' => $impor->Status->value, 'JumlahDiterapkan' => $impor->JumlahDiterapkan]);

            return $impor;
        });

        PengirimTugasImpor::KirimPenerapan($impor);

        return $impor->refresh();
    }
}
