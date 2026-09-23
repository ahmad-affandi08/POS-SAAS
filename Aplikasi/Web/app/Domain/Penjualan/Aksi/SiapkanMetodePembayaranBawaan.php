<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 5: Tunai selalu tersedia di kasir. Idempoten: dibuat sekali per tenant (kunci baris Tenant menjaga
 * kirim ganda). Dipanggil saat pendaftaran (F-00), penerapan template, penambahan metode pembayaran, dan perintah
 * `panduan-awal:siapkan-bawaan` untuk tenant lama. Konteks tenant diatur sementara bila belum ada.
 */
final class SiapkanMetodePembayaranBawaan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idTenant): void
    {
        $tenantSebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);

        try {
            DB::transaction(function () use ($idTenant): void {
                $this->penguncian->Kunci($idTenant);

                if (MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tunai->value)->exists()) {
                    return;
                }

                $tunai = MetodePembayaran::query()->create([
                    'Jenis' => JenisMetodePembayaran::Tunai,
                    'Nama' => JenisMetodePembayaran::Tunai->AmbilLabel(),
                    'Aktif' => true,
                    'Urutan' => 0,
                ]);
                $this->audit->Catat('metode-pembayaran.buat', $tunai, nilaiBaru: ['Jenis' => $tunai->Jenis->value, 'Nama' => $tunai->Nama], idTenant: $idTenant);
            });
        } finally {
            $tenantSebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($tenantSebelumnya);
        }
    }
}
