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
 * F-12: metode pembayaran Tempo (piutang) dibuat sistem saat tenant pertama kali memberi limit kredit ke pelanggan.
 * Idempoten per tenant (kunci baris Tenant); Tempo yang sudah ada (aktif atau dinonaktifkan) tidak dibuat ulang.
 * Aplikasi kasir hanya menampilkannya bila pelanggan dipilih.
 */
final class SiapkanMetodeTempo
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?int $idPengguna = null): void
    {
        $idTenant = $this->konteks->Wajib();

        DB::transaction(function () use ($idTenant, $idPengguna): void {
            $this->penguncian->Kunci($idTenant);

            if (MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tempo->value)->exists()) {
                return;
            }

            $tempo = MetodePembayaran::query()->create([
                'Jenis' => JenisMetodePembayaran::Tempo,
                'Nama' => 'Tempo',
                'Aktif' => true,
                'Urutan' => ((int) MetodePembayaran::query()->max('Urutan')) + 1,
            ]);
            $this->audit->Catat('metode-pembayaran.buat', $tempo, nilaiBaru: ['Jenis' => $tempo->Jenis->value, 'Nama' => $tempo->Nama], idPengguna: $idPengguna, idTenant: $idTenant);
        });
    }
}
