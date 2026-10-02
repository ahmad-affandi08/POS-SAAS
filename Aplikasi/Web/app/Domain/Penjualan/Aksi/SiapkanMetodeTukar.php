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
 * K-11: metode pembayaran "Tukar barang" dibuat sistem (idempoten per tenant) supaya aplikasi kasir punya Uuid-nya sejak
 * data awal dan bisa menukar barang secara offline: refund retur dan pembayaran barang pengganti memakai metode ini
 * (akun `KliringTukarBarang`). Tidak tampil sebagai pilihan bayar biasa di kasir.
 */
final class SiapkanMetodeTukar
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?int $idPengguna = null): MetodePembayaran
    {
        $ada = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tukar->value)->first();

        if ($ada !== null) {
            return $ada;
        }

        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $idPengguna): MetodePembayaran {
            $this->penguncian->Kunci($idTenant);
            $ada = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tukar->value)->first();

            if ($ada !== null) {
                return $ada;
            }

            $metode = MetodePembayaran::query()->create([
                'Jenis' => JenisMetodePembayaran::Tukar,
                'Nama' => 'Tukar barang',
                'Aktif' => true,
                'Urutan' => ((int) MetodePembayaran::query()->max('Urutan')) + 1,
            ]);
            $this->audit->Catat('metode-pembayaran.buat', $metode, nilaiBaru: ['Jenis' => $metode->Jenis->value, 'Nama' => $metode->Nama], idPengguna: $idPengguna, idTenant: $idTenant);

            return $metode;
        });
    }
}
