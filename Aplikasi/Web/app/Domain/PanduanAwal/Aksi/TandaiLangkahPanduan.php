<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01: menandai satu langkah wizard Selesai atau Dilewati. Tenant lama: setiap langkah bisa dilewati lalu dilanjutkan;
 * tenant baru (D-24, `Wajib`): hanya Perangkat yang boleh dilewati; Selesai
 * tidak pernah turun menjadi Dilewati. Satu baris progres per tenant (kunci Tenant + indeks unik sebagai penjaga
 * kedua). `idOutlet` mengisi outlet wizard bila progres belum menunjuk outlet.
 */
final class TandaiLangkahPanduan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
        private readonly PemakaianSku $pemakaianSku,
    ) {}

    public function Jalankan(LangkahPanduan $langkah, StatusLangkahPanduan $status, ?int $idOutlet = null): ProgresPanduanAwal
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $langkah, $status, $idOutlet): ProgresPanduanAwal {
            $this->penguncian->Kunci($idTenant);
            $progres = ProgresPanduanAwal::query()->firstOrCreate([], ['IdOutlet' => $idOutlet]);

            if ($progres->IdOutlet === null && $idOutlet !== null) {
                $progres->IdOutlet = $idOutlet;
            }

            $sekarang = $progres->AmbilStatus($langkah);

            // D-24: tenant baru tidak boleh melewati langkah wajib, dan langkah Produk butuh minimal satu produk.
            if ($progres->Wajib && $sekarang !== StatusLangkahPanduan::Selesai) {
                if ($status === StatusLangkahPanduan::Dilewati && $langkah->CekWajib()) {
                    throw new PelanggaranAturanBisnis('LangkahWajib', "Langkah {$langkah->AmbilJudul()} wajib diselesaikan supaya toko siap berjualan.");
                }

                if ($status === StatusLangkahPanduan::Selesai && $langkah === LangkahPanduan::Produk && $this->pemakaianSku->Hitung() === 0) {
                    throw new PelanggaranAturanBisnis('ProdukBelumAda', 'Tambahkan minimal satu produk dulu, misal dari produk contoh.');
                }
            }

            if ($sekarang->BisaBerubahKe($status)) {
                $progres->StatusLangkah = [
                    ...($progres->StatusLangkah ?? []),
                    $langkah->value => ['Status' => $status->value, 'Pada' => now()->toIso8601ZuluString()],
                ];

                if ($status === StatusLangkahPanduan::Dilewati) {
                    $this->audit->Catat('panduan-awal.lewati', $progres, nilaiLama: ['Status' => $sekarang->value], nilaiBaru: ['Langkah' => $langkah->value, 'Status' => $status->value]);
                }
            }

            if ($progres->isDirty()) {
                $progres->save();
            }

            return $progres;
        });
    }
}
