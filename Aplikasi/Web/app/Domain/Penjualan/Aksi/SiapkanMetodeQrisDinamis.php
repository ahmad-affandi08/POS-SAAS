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
 * Audit kemudahan pakai #24 (D-38): saat gerbang QRIS diaktifkan, metode bayar QRIS dinamis dibuat otomatis bila toko
 * belum punya, supaya pengguna tidak perlu tahu bahwa gerbang dan metode bayar adalah dua langkah terpisah. Metode yang
 * sudah ada (termasuk yang sengaja dinonaktifkan) tidak diubah. Biaya MDR mulai 0 dan bisa diisi di Metode pembayaran.
 * Mengembalikan metode baru, atau null bila toko sudah punya metode QRIS dinamis.
 */
final class SiapkanMetodeQrisDinamis
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly SiapkanMetodePembayaranBawaan $siapkanBawaan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(): ?MetodePembayaran
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant): ?MetodePembayaran {
            $this->penguncian->Kunci($idTenant);

            if (MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::QrisDinamis->value)->exists()) {
                return null;
            }

            $this->siapkanBawaan->Jalankan($idTenant);
            $namaAda = MetodePembayaran::query()->pluck('Nama')->map(fn (mixed $nama): string => mb_strtolower(trim((string) $nama)));
            $nama = $namaAda->contains('qris') ? 'QRIS dinamis' : 'QRIS';

            if ($namaAda->contains(mb_strtolower($nama))) {
                $nama = 'QRIS dinamis (gerbang)';
            }

            $metode = MetodePembayaran::query()->create([
                'Jenis' => JenisMetodePembayaran::QrisDinamis,
                'Nama' => $nama,
                'PersenBiaya' => '0',
                'Aktif' => true,
                'Urutan' => ((int) MetodePembayaran::query()->max('Urutan')) + 1,
            ]);
            $this->audit->Catat('metode-pembayaran.buat', $metode, nilaiBaru: ['Jenis' => $metode->Jenis->value, 'Nama' => $metode->Nama, 'Sumber' => 'gerbang-pembayaran.aktifkan']);

            return $metode;
        });
    }
}
